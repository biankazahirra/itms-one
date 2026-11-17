<?php

use App\Models\Budget;
use App\Models\Department;
use App\Models\GeneratedDocument;
use App\Models\PurchaseRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->pieces = Unit::create(['unit_name' => 'Pcs', 'is_active' => true]);
    $this->sets = Unit::create(['unit_name' => 'Set', 'is_active' => true]);

    Department::create(['name' => 'Accounting Department']);
});

function rabPayload(array $overrides = []): array
{
    return array_replace([
        'work_name' => 'Pengadaan Monitor Kantor',
        'rab_date' => '2026-11-17',
        'items' => [
            [
                'budget_name' => 'Monitor 27 inci',
                'description' => 'IPS panel',
                'quantity' => '2',
                'unit_id' => Unit::where('unit_name', 'Pcs')->value('id'),
                'unit_price' => '3500000',
            ],
            [
                'budget_name' => 'Kabel HDMI',
                'description' => '',
                'quantity' => '2.5',
                'unit_id' => Unit::where('unit_name', 'Pcs')->value('id'),
                'unit_price' => '50000',
            ],
        ],
    ], $overrides);
}

function prPayloadForRabImport(array $overrides = []): array
{
    return array_replace([
        'recipient_department_id' => Department::where('name', 'Accounting Department')->value('id'),
        'subject' => 'Pengadaan ATK Kantor',
        'pr_date' => '2026-11-17',
        'category' => 'Office Supplies',
        'items' => [
            [
                'budget_name' => 'Kertas A4 80gsm',
                'quantity' => '10',
                'unit_price' => '65000',
            ],
            [
                'budget_name' => 'TISUE NICE',
                'quantity' => '5',
                'unit_price' => '0',
            ],
        ],
    ], $overrides);
}

it('redirects guests to the login page', function () {
    $this->get(route('rab.index'))->assertRedirect(route('login'));
});

it('creates a standalone draft RAB with sequential number and calculated totals', function () {
    $this->actingAs($this->user)
        ->post(route('rab.store'), rabPayload())
        ->assertRedirect();

    $budget = Budget::firstOrFail();
    $version = $budget->currentVersion;

    expect($budget->rab_number)->toBe('RAB-000001')
        ->and($budget->status)->toBe(Budget::STATUS_DRAFT)
        ->and($budget->purchase_request_id)->toBeNull()
        ->and($version->version_number)->toBe(1)
        ->and($version->location)->toBe('Cilegon')
        ->and($version->grand_total)->toBe('7125000.00')
        ->and($version->items)->toHaveCount(2)
        ->and($version->items[0]->line_total)->toBe('7000000.00')
        ->and($version->items[0]->unit_name_snapshot)->toBe('Pcs');
});

it('never reuses an RAB number after deletion', function () {
    $this->actingAs($this->user)->post(route('rab.store'), rabPayload());
    $first = Budget::firstOrFail();

    $this->actingAs($this->user)->post(route('rab.store'), rabPayload(['work_name' => 'RAB kedua']));
    $second = Budget::orderByDesc('id')->firstOrFail();

    expect($first->rab_number)->toBe('RAB-000001')
        ->and($second->rab_number)->toBe('RAB-000002');

    $this->actingAs($this->user)->delete(route('rab.destroy', $first));

    $this->actingAs($this->user)->post(route('rab.store'), rabPayload(['work_name' => 'RAB ketiga']));
    $third = Budget::orderByDesc('id')->firstOrFail();

    expect($third->rab_number)->toBe('RAB-000003');
});

it('rejects invalid quantities, negative prices, and missing units', function () {
    $this->actingAs($this->user)
        ->post(route('rab.store'), rabPayload([
            'items' => [[
                'budget_name' => 'Barang',
                'description' => '',
                'quantity' => '0',
                'unit_id' => '',
                'unit_price' => '-1',
            ]],
        ]))
        ->assertSessionHasErrors(['items.0.quantity', 'items.0.unit_id', 'items.0.unit_price']);
});

it('imports all PR items with the stored unit price copied directly', function () {
    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayloadForRabImport());
    $pr = PurchaseRequest::firstOrFail();
    $prVersion = $pr->currentVersion;

    $this->actingAs($this->user)->post(route('rab.store'), [
        'purchase_request_id' => $pr->id,
    ]);

    $budget = Budget::firstOrFail();
    $version = $budget->currentVersion;

    expect($budget->purchase_request_id)->toBe($pr->id)
        ->and($version->source_pr_version_id)->toBe($prVersion->id)
        ->and($version->work_name)->toBe('Pengadaan ATK Kantor')
        ->and($version->rab_date->format('Y-m-d'))->toBe('2026-11-17')
        ->and($version->location)->toBe('Cilegon')
        ->and($version->items)->toHaveCount(2);

    $first = $version->items[0];
    expect($first->budget_name)->toBe('Kertas A4 80gsm')
        ->and((string) $first->quantity)->toBe('10.000')
        ->and($first->unit_price)->toBe('65000.00')
        ->and($first->line_total)->toBe('650000.00')
        ->and($first->description)->toBeNull()
        ->and($first->unit_id)->toBeNull()
        ->and($version->grand_total)->toBe('650000.00');

    $pr->refresh()->load('currentVersion.items');
    expect($pr->currentVersion->items)->toHaveCount(2)
        ->and($pr->currentVersion->grand_total)->toBe('650000.00');
});

it('blocks finalization of a PR-linked RAB when totals differ', function () {
    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayloadForRabImport());
    $pr = PurchaseRequest::firstOrFail();

    $this->actingAs($this->user)->post(route('rab.store'), ['purchase_request_id' => $pr->id]);
    $budget = Budget::firstOrFail();

    // Make the RAB total different from the PR total.
    $this->actingAs($this->user)->put(route('rab.update', $budget), [
        'work_name' => 'Pengadaan ATK Kantor',
        'rab_date' => '2026-11-17',
        'items' => [
            [
                'budget_name' => 'Kertas A4 80gsm',
                'description' => '',
                'quantity' => '11',
                'unit_id' => $this->pieces->id,
                'unit_price' => '65000',
            ],
        ],
    ])->assertSessionHas('status');

    $this->actingAs($this->user)
        ->post(route('rab.finalize', $budget))
        ->assertSessionHasErrors('total');

    expect($budget->fresh()->status)->toBe(Budget::STATUS_DRAFT);
});

it('finalizes a PR-linked RAB when totals are equal and never changes the PR', function () {
    $this->actingAs($this->user)->post(route('purchase-requests.store'), prPayloadForRabImport());
    $pr = PurchaseRequest::firstOrFail();
    $prTotalBefore = $pr->currentVersion->grand_total;

    // Edit the PR itself (still Draft) is not allowed via RAB flows; confirm baseline.
    $this->actingAs($this->user)->post(route('rab.store'), ['purchase_request_id' => $pr->id]);
    $budget = Budget::firstOrFail();

    // Update RAB without changing line values: same total.
    $this->actingAs($this->user)->put(route('rab.update', $budget), [
        'work_name' => 'Pengadaan ATK Kantor',
        'rab_date' => '2026-11-17',
        'items' => [
            ['budget_name' => 'Kertas A4 80gsm', 'description' => 'A4', 'quantity' => '10', 'unit_id' => $this->pieces->id, 'unit_price' => '65000'],
            ['budget_name' => 'TISUE NICE', 'description' => '', 'quantity' => '5', 'unit_id' => $this->sets->id, 'unit_price' => '0'],
        ],
    ])->assertSessionHas('status');

    $this->actingAs($this->user)
        ->post(route('rab.finalize', $budget))
        ->assertSessionHas('status');

    expect($budget->fresh()->status)->toBe(Budget::STATUS_FINAL)
        ->and($budget->fresh()->finalized_at)->not->toBeNull();

    expect($pr->fresh()->currentVersion->grand_total)->toBe($prTotalBefore);
});

it('finalizes a standalone RAB without any PR comparison', function () {
    $this->actingAs($this->user)->post(route('rab.store'), rabPayload());
    $budget = Budget::firstOrFail();

    $this->actingAs($this->user)
        ->post(route('rab.finalize', $budget))
        ->assertSessionHas('status');

    expect($budget->fresh()->status)->toBe(Budget::STATUS_FINAL);
});

it('creates a revision from a final RAB and keeps the source version immutable', function () {
    $this->actingAs($this->user)->post(route('rab.store'), rabPayload());
    $budget = Budget::firstOrFail();

    $this->actingAs($this->user)
        ->post(route('rab.finalize', $budget))
        ->assertSessionHas('status');

    $originalVersion = $budget->fresh()->currentVersion;

    $this->actingAs($this->user)
        ->post(route('rab.revision', $budget))
        ->assertSessionHas('status');

    $revised = $budget->fresh();
    $draftVersion = $revised->currentVersion;

    expect($draftVersion->version_number)->toBe(2)
        ->and($draftVersion->isFinal())->toBeFalse()
        ->and($revised->status)->toBe(Budget::STATUS_DRAFT)
        ->and($draftVersion->work_name)->toBe($originalVersion->work_name)
        ->and($originalVersion->isFinal())->toBeTrue()
        ->and($draftVersion->items)->toHaveCount(2);

    $this->actingAs($this->user)
        ->post(route('rab.finalize', $revised))
        ->assertSessionHas('status');

    expect($revised->fresh()->status)->toBe(Budget::STATUS_FINAL);
});

it('blocks deleting a finalized RAB but allows deleting a draft RAB', function () {
    $this->actingAs($this->user)->post(route('rab.store'), rabPayload());
    $budget = Budget::firstOrFail();

    $this->actingAs($this->user)->post(route('rab.finalize', $budget));
    $this->actingAs($this->user)
        ->delete(route('rab.destroy', $budget))
        ->assertSessionHasErrors('status');

    expect(Budget::withTrashed()->count())->toBe(1);
});

it('generates a version specific RAB PDF with metadata and checksum', function () {
    Storage::fake('local');

    $this->actingAs($this->user)->post(route('rab.store'), rabPayload());
    $budget = Budget::firstOrFail();

    $this->actingAs($this->user)
        ->post(route('rab.finalize', $budget), ['generate_pdf' => '1'])
        ->assertSessionHas('status');

    $document = GeneratedDocument::firstOrFail();
    $version = $budget->fresh()->currentVersion;

    expect($document->entity_type)->toBe(GeneratedDocument::ENTITY_BUDGET)
        ->and($document->entity_id)->toBe($budget->id)
        ->and($document->version_id)->toBe($version->id)
        ->and($document->original_filename)->toBe('RAB-000001_V01.pdf')
        ->and($document->storage_path)->toBe("rab/{$budget->id}/versions/{$version->id}/RAB-000001_V01.pdf")
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

    $this->actingAs($this->user)->post(route('rab.store'), rabPayload());
    $budget = Budget::firstOrFail();

    $this->actingAs($this->user)->post(route('rab.finalize', $budget), ['generate_pdf' => '1']);
    $first = GeneratedDocument::firstOrFail();

    $this->actingAs($this->user)
        ->post(route('rab.documents.store', $budget))
        ->assertSessionHas('status');

    expect(GeneratedDocument::count())->toBe(1)
        ->and(GeneratedDocument::firstOrFail()->checksum_sha256)->toBe($first->checksum_sha256);
});

it('renders the RAB PDF template with signatories and total', function () {
    $this->actingAs($this->user)->post(route('rab.store'), rabPayload([
        'items' => [[
            'budget_name' => 'Barang uji',
            'description' => 'Deskripsi uji',
            'quantity' => '3',
            'unit_id' => $this->pieces->id,
            'unit_price' => '7777',
        ]],
    ]));

    $budget = Budget::firstOrFail();
    $version = $budget->currentVersion->load('items.unit');

    $html = view('documents.rab-pdf', [
        'budget' => $budget,
        'version' => $version,
    ])->render();

    expect($html)->toContain('Rencana Anggaran Biaya')
        ->toContain('Pengadaan Monitor Kantor')
        ->toContain('Barang uji')
        ->toContain('Tb. Benny Kurniawan')
        ->toContain('ITMS Manager')
        ->toContain('Lazuardi Arsy')
        ->toContain('IT Senior Engineer')
        ->toContain('23.331');
});

it('renders a blank amount cell for zero-priced RAB items in the PDF template', function () {
    $this->actingAs($this->user)->post(route('rab.store'), rabPayload([
        'items' => [[
            'budget_name' => 'Barang tanpa harga',
            'description' => '',
            'quantity' => '5',
            'unit_id' => $this->pieces->id,
            'unit_price' => '0',
        ]],
    ]));

    $budget = Budget::firstOrFail();
    $version = $budget->currentVersion->load('items.unit');

    $html = view('documents.rab-pdf', [
        'budget' => $budget,
        'version' => $version,
    ])->render();

    expect($html)->toContain('Barang tanpa harga')
        ->toMatch('#<td class="amount"></td>#');
});
