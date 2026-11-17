<?php

use App\Models\Department;
use App\Models\GeneratedDocument;
use App\Models\PurchaseRequest;
use App\Models\RecycleBinItem;
use App\Models\User;
use App\Services\PurchaseRequestService;
use App\Services\RecycleBinService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->accounting = Department::create(['name' => 'Accounting Department']);
    $this->humanCapital = Department::create(['name' => 'Human Capital Department']);
    Department::create(['name' => 'ITMS']);
});

function prPayload(array $overrides = []): array
{
    return array_replace([
        'recipient_department_id' => Department::where('name', 'Accounting Department')->value('id'),
        'subject' => 'Pengadaan Laptop Tim IT',
        'pr_date' => '2026-11-17',
        'category' => 'Fixed Asset',
        'items' => [
            [
                'budget_name' => 'Laptop 14 inci',
                'quantity' => '2',
                'unit_price' => '8500000',
            ],
            [
                'budget_name' => 'Docking station',
                'quantity' => '2.5',
                'unit_price' => '1200000',
            ],
        ],
    ], $overrides);
}

it('redirects guests to the login page', function () {
    $this->get(route('purchase-requests.index'))->assertRedirect(route('login'));
});

it('creates a draft PR with a sequential number and calculated totals', function () {
    $this->actingAs($this->user)
        ->post(route('purchase-requests.store'), prPayload())
        ->assertRedirect();

    $purchaseRequest = PurchaseRequest::firstOrFail();
    $version = $purchaseRequest->currentVersion;

    expect($purchaseRequest->pr_number)->toBe('PR-000001')
        ->and($purchaseRequest->status)->toBe(PurchaseRequest::STATUS_DRAFT)
        ->and($version->version_number)->toBe(1)
        ->and($version->grand_total)->toBe('20000000.00')
        ->and($version->items)->toHaveCount(2)
        ->and($version->items[1]->estimated_total)->toBe('3000000.00');

    $this->assertDatabaseHas('activity_logs', [
        'entity_type' => 'purchase_request',
        'entity_id' => $purchaseRequest->id,
        'action' => 'created',
    ]);
});

it('never reuses a PR number after deletion', function () {
    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayload());
    $first = PurchaseRequest::firstOrFail();

    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayload(['subject' => 'PR kedua']));
    $second = PurchaseRequest::orderByDesc('id')->firstOrFail();

    expect($first->pr_number)->toBe('PR-000001')
        ->and($second->pr_number)->toBe('PR-000002');

    $this->actingAs($this->user)->delete(route('purchase-requests.destroy', $first));

    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayload(['subject' => 'PR ketiga']));
    $third = PurchaseRequest::orderByDesc('id')->firstOrFail();

    expect($third->pr_number)->toBe('PR-000003');
});

it('rejects invalid quantities, negative prices, and the sender department as recipient', function () {
    $this->actingAs($this->user)
        ->post(route('purchase-requests.store'), prPayload([
            'items' => [[
                'budget_name' => 'Barang',
                'quantity' => '0',
                'unit_price' => '-1',
            ]],
        ]))
        ->assertSessionHasErrors(['items.0.quantity', 'items.0.unit_price']);

    $this->actingAs($this->user)
        ->post(route('purchase-requests.store'), prPayload([
            'items' => [[
                'budget_name' => 'Barang',
                'quantity' => '1.2345',
                'unit_price' => '1000',
            ]],
        ]))
        ->assertSessionHasErrors(['items.0.quantity']);

    $this->actingAs($this->user)
        ->post(route('purchase-requests.store'), prPayload([
            'recipient_department_id' => Department::where('name', 'ITMS')->value('id'),
        ]))
        ->assertSessionHasErrors(['recipient_department_id']);

    expect(PurchaseRequest::count())->toBe(0);
});

it('allows a zero unit price with a review warning', function () {
    $this->actingAs($this->user)
        ->post(route('purchase-requests.store'), prPayload([
            'items' => [[
                'budget_name' => 'Barang hibah',
                'quantity' => '1',
                'unit_price' => '0',
            ]],
        ]))
        ->assertSessionHas('warning');

    $purchaseRequest = PurchaseRequest::firstOrFail();

    expect($purchaseRequest->currentVersion->grand_total)->toBe('0.00');
});

it('updates only draft versions and recalculates totals', function () {
    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayload());
    $purchaseRequest = PurchaseRequest::firstOrFail();

    $this->actingAs($this->user)
        ->put(route('purchase-requests.update', $purchaseRequest), prPayload([
            'subject' => 'Revisi draft',
            'items' => [[
                'budget_name' => 'Laptop 14 inci',
                'quantity' => '3',
                'unit_price' => '8500000',
            ]],
        ]))
        ->assertRedirect(route('purchase-requests.show', $purchaseRequest));

    $purchaseRequest->refresh();

    expect($purchaseRequest->currentVersion->subject)->toBe('Revisi draft')
        ->and($purchaseRequest->currentVersion->grand_total)->toBe('25500000.00');
});

it('finalizes a draft and blocks later edits', function () {
    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayload());
    $purchaseRequest = PurchaseRequest::firstOrFail();

    $this->actingAs($this->user)
        ->post(route('purchase-requests.finalize', $purchaseRequest), ['generate_pdf' => '0'])
        ->assertRedirect(route('purchase-requests.show', $purchaseRequest));

    $purchaseRequest->refresh();

    expect($purchaseRequest->status)->toBe(PurchaseRequest::STATUS_FINAL)
        ->and($purchaseRequest->finalized_at)->not->toBeNull()
        ->and($purchaseRequest->currentVersion->finalized_at)->not->toBeNull()
        ->and($purchaseRequest->currentVersion->grand_total)->toBe('20000000.00');

    $this->actingAs($this->user)
        ->put(route('purchase-requests.update', $purchaseRequest), prPayload(['subject' => 'Tidak boleh']))
        ->assertSessionHasErrors('status');

    expect($purchaseRequest->fresh()->currentVersion->subject)->toBe('Pengadaan Laptop Tim IT');
});

it('blocks finalization when the version has no items', function () {
    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayload());
    $purchaseRequest = PurchaseRequest::firstOrFail();

    $purchaseRequest->currentVersion->items()->delete();

    expect(fn () => app(PurchaseRequestService::class)->finalize($purchaseRequest, $this->user))
        ->toThrow(ValidationException::class);

    expect($purchaseRequest->fresh()->status)->toBe(PurchaseRequest::STATUS_DRAFT);
});

it('creates a revision that copies the final version without changing it', function () {
    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayload());
    $purchaseRequest = PurchaseRequest::firstOrFail();

    $this->actingAs($this->user)->post(route('purchase-requests.finalize', $purchaseRequest), ['generate_pdf' => '0']);

    $finalVersion = $purchaseRequest->fresh()->currentVersion;

    $this->actingAs($this->user)
        ->post(route('purchase-requests.revision', $purchaseRequest))
        ->assertRedirect(route('purchase-requests.edit', $purchaseRequest));

    $purchaseRequest->refresh();
    $draft = $purchaseRequest->currentVersion;

    expect($purchaseRequest->status)->toBe(PurchaseRequest::STATUS_DRAFT)
        ->and($purchaseRequest->pr_number)->toBe('PR-000001')
        ->and($draft->version_number)->toBe(2)
        ->and($draft->finalized_at)->toBeNull()
        ->and($draft->items)->toHaveCount(2)
        ->and($draft->items->first()->unit_price)->toBe('8500000.00');

    $finalVersion->refresh();

    expect($finalVersion->finalized_at)->not->toBeNull()
        ->and($finalVersion->items)->toHaveCount(2)
        ->and($purchaseRequest->versions()->count())->toBe(2);
});

it('archives only final PRs and blocks revision of archived records', function () {
    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayload());
    $purchaseRequest = PurchaseRequest::firstOrFail();

    $this->actingAs($this->user)->post(route('purchase-requests.finalize', $purchaseRequest), ['generate_pdf' => '0']);
    $this->actingAs($this->user)->post(route('purchase-requests.archive', $purchaseRequest));

    $purchaseRequest->refresh();

    expect($purchaseRequest->status)->toBe(PurchaseRequest::STATUS_ARCHIVED)
        ->and($purchaseRequest->archived_at)->not->toBeNull();

    $this->actingAs($this->user)
        ->post(route('purchase-requests.revision', $purchaseRequest))
        ->assertSessionHasErrors('status');
});

it('moves a draft PR to the recycle bin and restores it', function () {
    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayload());
    $purchaseRequest = PurchaseRequest::firstOrFail();

    $this->actingAs($this->user)
        ->delete(route('purchase-requests.destroy', $purchaseRequest))
        ->assertRedirect(route('purchase-requests.index'));

    expect(PurchaseRequest::find($purchaseRequest->id))->toBeNull();

    $item = RecycleBinItem::where('entity_type', 'purchase_request')
        ->where('entity_id', $purchaseRequest->id)
        ->firstOrFail();

    app(RecycleBinService::class)->restore($item);

    expect(PurchaseRequest::find($purchaseRequest->id))->not->toBeNull()
        ->and(RecycleBinItem::count())->toBe(0);
});

it('protects PR records with finalized history from purge and deletion', function () {
    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayload());
    $purchaseRequest = PurchaseRequest::firstOrFail();

    expect($purchaseRequest->canBePurged())->toBeTrue();

    $this->actingAs($this->user)->post(route('purchase-requests.finalize', $purchaseRequest), ['generate_pdf' => '0']);
    expect($purchaseRequest->fresh()->canBePurged())->toBeFalse();

    $this->actingAs($this->user)->post(route('purchase-requests.revision', $purchaseRequest));
    $purchaseRequest->refresh();

    expect($purchaseRequest->status)->toBe(PurchaseRequest::STATUS_DRAFT)
        ->and($purchaseRequest->canBePurged())->toBeFalse();

    $this->actingAs($this->user)
        ->delete(route('purchase-requests.destroy', $purchaseRequest))
        ->assertSessionHasErrors('status');
});

it('generates a version specific PDF with metadata and checksum', function () {
    Storage::fake('local');

    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayload());
    $purchaseRequest = PurchaseRequest::firstOrFail();

    $this->actingAs($this->user)
        ->post(route('purchase-requests.finalize', $purchaseRequest), ['generate_pdf' => '1'])
        ->assertSessionHas('status');

    $document = GeneratedDocument::firstOrFail();
    $version = $purchaseRequest->fresh()->currentVersion;

    expect($document->entity_type)->toBe(GeneratedDocument::ENTITY_PURCHASE_REQUEST)
        ->and($document->entity_id)->toBe($purchaseRequest->id)
        ->and($document->version_id)->toBe($version->id)
        ->and($document->original_filename)->toBe('PR-000001_V01.pdf')
        ->and($document->storage_path)->toBe("pr/{$purchaseRequest->id}/versions/{$version->id}/PR-000001_V01.pdf")
        ->and($document->mime_type)->toBe('application/pdf')
        ->and($document->checksum_sha256)->toHaveLength(64)
        ->and($document->file_size)->toBeGreaterThan(1000);

    Storage::disk('local')->assertExists($document->storage_path);

    $this->actingAs($this->user)
        ->get(route('documents.download', $document))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('does not overwrite a PDF when generated twice for the same version', function () {
    Storage::fake('local');

    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayload());
    $purchaseRequest = PurchaseRequest::firstOrFail();

    $this->actingAs($this->user)->post(route('purchase-requests.finalize', $purchaseRequest), ['generate_pdf' => '1']);
    $first = GeneratedDocument::firstOrFail();

    $this->actingAs($this->user)
        ->post(route('purchase-requests.documents.store', $purchaseRequest))
        ->assertSessionHas('status');

    expect(GeneratedDocument::count())->toBe(1)
        ->and(GeneratedDocument::firstOrFail()->checksum_sha256)->toBe($first->checksum_sha256);
});

it('keeps unit prices out of the PR PDF template', function () {
    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayload([
        'items' => [[
            'budget_name' => 'Barang uji',
            'quantity' => '3',
            'unit_price' => '7777',
        ]],
    ]));

    $purchaseRequest = PurchaseRequest::firstOrFail();
    $version = $purchaseRequest->currentVersion->load(['items', 'department']);

    $html = view('documents.pr-pdf', [
        'purchaseRequest' => $purchaseRequest,
        'version' => $version,
    ])->render();

    expect($html)->toContain('GL Account')
        ->toContain('Estimasi Harga')
        ->toContain('23.331')
        ->not->toContain('Harga Satuan')
        ->not->toContain('7.777');
});

it('renders a blank estimated price cell for zero-priced items in the PR PDF template', function () {
    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayload([
        'items' => [
            [
                'budget_name' => 'Barang tanpa harga',
                'quantity' => '5',
                'unit_price' => '0',
            ],
        ],
    ]));

    $purchaseRequest = PurchaseRequest::firstOrFail();
    $version = $purchaseRequest->currentVersion->load(['items', 'department']);

    $html = view('documents.pr-pdf', [
        'purchaseRequest' => $purchaseRequest,
        'version' => $version,
    ])->render();

    expect($html)->toContain('Barang tanpa harga')
        ->toMatch('#<td class="money"></td>#');
});

it('filters the PR list by search term and status', function () {
    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayload());
    $first = PurchaseRequest::firstOrFail();

    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayload(['subject' => 'Pengadaan Meja']));
    $second = PurchaseRequest::orderByDesc('id')->firstOrFail();

    $this->actingAs($this->user)->post(route('purchase-requests.finalize', $second), ['generate_pdf' => '0']);

    $this->actingAs($this->user)
        ->get(route('purchase-requests.index', ['q' => 'Meja']))
        ->assertOk()
        ->assertSee('Pengadaan Meja')
        ->assertDontSee('Pengadaan Laptop Tim IT');

    $this->actingAs($this->user)
        ->get(route('purchase-requests.index', ['status' => 'Draft']))
        ->assertOk()
        ->assertSee($first->pr_number);
});
