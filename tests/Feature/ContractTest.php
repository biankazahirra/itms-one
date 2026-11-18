<?php

use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\ContractActivityLog;
use App\Models\ContractAttachment;
use App\Models\RecycleBinItem;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ContractService;
use App\Services\ContractStatusService;
use App\Services\RecycleBinService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->vendor = Vendor::create([
        'company_name' => 'PT Mitra Teknologi',
        'company_address' => 'Jl. Industri No. 1, Cilegon',
        'npwp' => '01.234.567.8-901.000',
        'phone' => '0254-123456',
        'email' => 'kontak@mitra.test',
        'is_active' => true,
    ]);

    $this->otherVendor = Vendor::create([
        'company_name' => 'PT Sumber Listrik',
        'company_address' => 'Jl. Raya Anyer KM 5, Cilegon',
        'is_active' => true,
    ]);
});

function contractPayload(array $overrides = []): array
{
    return array_replace([
        'po_number' => 'PO/ITMS/2026/001',
        'contract_date' => '2026-11-17',
        'vendor_id' => test()->vendor->id,
        'work_name' => 'Pengadaan Jaringan Kantor',
        'representative_name' => 'Budi Santoso',
        'representative_position' => 'Direktur',
        'start_date' => '2026-11-01',
        'end_date' => '2027-11-01',
        'contract_value' => '120000000',
        'ppn_status' => 'Belum termasuk PPN',
        'payment_method' => 'Bulanan',
        'monthly_payment_amount' => '10000000',
    ], $overrides);
}

it('redirects guests to the login page', function () {
    $this->get(route('contracts.index'))->assertRedirect(route('login'));
});

it('creates a monthly contract with activity history', function () {
    $this->actingAs($this->user)
        ->post(route('contracts.store'), contractPayload())
        ->assertRedirect();

    $contract = Contract::firstOrFail();

    expect($contract->po_number)->toBe('PO/ITMS/2026/001')
        ->and($contract->vendor_id)->toBe($this->vendor->id)
        ->and($contract->status)->toBe(Contract::STATUS_ACTIVE)
        ->and($contract->monthly_payment_amount)->toBe('10000000.00')
        ->and($contract->full_financing_amount)->toBeNull()
        ->and($contract->termins()->count())->toBe(0);

    expect(ContractActivityLog::where('contract_id', $contract->id)->where('action', 'created')->count())->toBe(1)
        ->and(ActivityLog::where('entity_type', 'contract')->where('entity_id', $contract->id)->count())->toBe(1);
});

it('rejects a duplicate PO number through application validation', function () {
    $this->actingAs($this->user)->post(route('contracts.store'), contractPayload());

    $this->actingAs($this->user)
        ->post(route('contracts.store'), contractPayload(['work_name' => 'Kontrak kedua']))
        ->assertSessionHasErrors('po_number');

    expect(Contract::count())->toBe(1);
});

it('enforces PO number uniqueness at the database level', function () {
    Contract::create(contractPayload() + [
        'status' => Contract::STATUS_ACTIVE,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]);

    expect(fn () => Contract::create(contractPayload() + [
        'status' => Contract::STATUS_ACTIVE,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]))->toThrow(QueryException::class);
});

it('validates contract dates and values', function () {
    $this->actingAs($this->user)
        ->post(route('contracts.store'), contractPayload([
            'end_date' => '2026-10-01',
            'contract_value' => '0',
        ]))
        ->assertSessionHasErrors(['end_date', 'contract_value']);
});

it('requires the amount for the selected payment method and clears the others', function () {
    $this->actingAs($this->user)
        ->post(route('contracts.store'), contractPayload(['monthly_payment_amount' => '']))
        ->assertSessionHasErrors('monthly_payment_amount');

    $this->actingAs($this->user)
        ->post(route('contracts.store'), contractPayload([
            'po_number' => 'PO/ITMS/2026/002',
            'payment_method' => 'Full Financing',
            'monthly_payment_amount' => '',
            'full_financing_amount' => '120000000',
        ]))
        ->assertRedirect();

    $contract = Contract::where('po_number', 'PO/ITMS/2026/002')->firstOrFail();

    expect($contract->monthly_payment_amount)->toBeNull()
        ->and($contract->full_financing_amount)->toBe('120000000.00');
});

it('rejects a termin schedule that does not total 100 percent', function () {
    $this->actingAs($this->user)
        ->post(route('contracts.store'), contractPayload([
            'payment_method' => 'Termin',
            'monthly_payment_amount' => '',
            'termins' => [
                ['termin_name' => 'Termin 1', 'percentage' => '40'],
                ['termin_name' => 'Termin 2', 'percentage' => '50'],
            ],
        ]))
        ->assertSessionHasErrors('termins');

    expect(Contract::count())->toBe(0);
});

it('calculates termin amounts and puts the rounding remainder on the final termin', function () {
    $this->actingAs($this->user)
        ->post(route('contracts.store'), contractPayload([
            'po_number' => 'PO/ITMS/2026/003',
            'contract_value' => '1000000',
            'payment_method' => 'Termin',
            'monthly_payment_amount' => '',
            'termins' => [
                ['termin_name' => 'Termin 1', 'percentage' => '33.3333'],
                ['termin_name' => 'Termin 2', 'percentage' => '33.3333'],
                ['termin_name' => 'Termin 3', 'percentage' => '33.3334'],
            ],
        ]))
        ->assertRedirect();

    $contract = Contract::where('po_number', 'PO/ITMS/2026/003')->firstOrFail();
    $termins = $contract->termins()->orderBy('sort_order')->get();

    expect($termins)->toHaveCount(3)
        ->and($termins[0]->nominal_amount)->toBe('333333.00')
        ->and($termins[1]->nominal_amount)->toBe('333333.00')
        ->and($termins[2]->nominal_amount)->toBe('333334.00')
        ->and($termins->sum(fn ($termin) => (float) $termin->nominal_amount))->toBe(1000000.0);
});

it('sets inactive status automatically after the end date without overwriting archived', function () {
    $service = app(ContractStatusService::class);

    $expired = Contract::create(contractPayload([
        'po_number' => 'PO/ITMS/2026/004',
        'start_date' => now()->subMonths(2)->format('Y-m-d'),
        'end_date' => now()->subDay()->format('Y-m-d'),
        'status' => Contract::STATUS_ACTIVE,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]));

    $service->sync($expired);
    expect($expired->fresh()->status)->toBe(Contract::STATUS_INACTIVE)
        ->and($expired->fresh()->isSelectableForLhstp())->toBeTrue();

    $archived = Contract::create(contractPayload([
        'po_number' => 'PO/ITMS/2026/005',
        'start_date' => now()->subMonths(2)->format('Y-m-d'),
        'end_date' => now()->subDay()->format('Y-m-d'),
        'status' => Contract::STATUS_ARCHIVED,
        'archived_at' => now(),
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]));

    $service->sync($archived);
    expect($archived->fresh()->status)->toBe(Contract::STATUS_ARCHIVED)
        ->and($archived->fresh()->isSelectableForLhstp())->toBeFalse();
});

it('records before and after values when a contract is edited', function () {
    $this->actingAs($this->user)->post(route('contracts.store'), contractPayload());
    $contract = Contract::firstOrFail();

    $this->actingAs($this->user)
        ->put(route('contracts.update', $contract), contractPayload([
            'contract_value' => '150000000',
            'representative_name' => 'Siti Aminah',
        ]))
        ->assertRedirect();

    $contract->refresh();
    $log = ContractActivityLog::where('contract_id', $contract->id)->where('action', 'updated')->firstOrFail();

    expect($contract->contract_value)->toBe('150000000.00')
        ->and($contract->representative_name)->toBe('Siti Aminah')
        ->and($log->before_values['contract_value'])->toBe('120000000.00')
        ->and($log->after_values['contract_value'])->toBe('150000000.00');
});

it('archives and unarchives a contract', function () {
    $this->actingAs($this->user)->post(route('contracts.store'), contractPayload());
    $contract = Contract::firstOrFail();

    $this->actingAs($this->user)->post(route('contracts.archive', $contract))->assertRedirect();
    expect($contract->fresh()->status)->toBe(Contract::STATUS_ARCHIVED);

    $this->actingAs($this->user)->post(route('contracts.unarchive', $contract))->assertRedirect();
    expect($contract->fresh()->status)->toBe(Contract::STATUS_ACTIVE)
        ->and($contract->fresh()->archived_at)->toBeNull();
});

it('moves a deleted contract to the shared recycle bin and restores it', function () {
    $this->actingAs($this->user)->post(route('contracts.store'), contractPayload());
    $contract = Contract::firstOrFail();

    $this->actingAs($this->user)->delete(route('contracts.destroy', $contract))->assertRedirect();

    expect($contract->fresh()->trashed())->toBeTrue()
        ->and(RecycleBinItem::where('entity_type', 'contract')->where('entity_id', $contract->id)->count())->toBe(1);

    app(RecycleBinService::class)->restore(
        RecycleBinItem::where('entity_type', 'contract')->firstOrFail()
    );

    expect($contract->fresh()->trashed())->toBeFalse();
});

it('stores PDF attachments privately and serves them through an authorized route', function () {
    Storage::fake('local');

    $this->actingAs($this->user)->post(route('contracts.store'), contractPayload([
        'attachments' => [UploadedFile::fake()->create('kontrak-utama.pdf', 120, 'application/pdf')],
    ]))->assertRedirect();

    $contract = Contract::firstOrFail();
    $attachment = ContractAttachment::firstOrFail();

    expect($attachment->contract_id)->toBe($contract->id)
        ->and($attachment->original_filename)->toBe('kontrak-utama.pdf')
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($attachment->checksum_sha256)->toHaveLength(64);

    Storage::disk('local')->assertExists($attachment->storage_path);

    $this->actingAs($this->user)
        ->get(route('contracts.attachments.download', [$contract, $attachment]))
        ->assertOk();
});

it('rejects non PDF attachments', function () {
    Storage::fake('local');

    $this->actingAs($this->user)
        ->post(route('contracts.store'), contractPayload([
            'attachments' => [UploadedFile::fake()->create('gambar.png', 20, 'image/png')],
        ]))
        ->assertSessionHasErrors('attachments.0');

    expect(Contract::count())->toBe(0);
});

it('removes an attachment without deleting the contract', function () {
    Storage::fake('local');

    $this->actingAs($this->user)->post(route('contracts.store'), contractPayload([
        'attachments' => [UploadedFile::fake()->create('addendum.pdf', 50, 'application/pdf')],
    ]));

    $contract = Contract::firstOrFail();
    $attachment = ContractAttachment::firstOrFail();

    $this->actingAs($this->user)
        ->delete(route('contracts.attachments.destroy', [$contract, $attachment]))
        ->assertRedirect();

    expect(ContractAttachment::count())->toBe(0)
        ->and(Contract::count())->toBe(1);

    Storage::disk('local')->assertMissing($attachment->storage_path);
});

it('renders contract list, detail, create, and edit pages', function () {
    $this->actingAs($this->user)->post(route('contracts.store'), contractPayload());
    $contract = Contract::firstOrFail();

    $this->actingAs($this->user)->get(route('contracts.index'))->assertOk()
        ->assertSee('PO/ITMS/2026/001');
    $this->actingAs($this->user)->get(route('contracts.show', $contract))->assertOk()
        ->assertSee('Budi Santoso');
    $this->actingAs($this->user)->get(route('contracts.create'))->assertOk();
    $this->actingAs($this->user)->get(route('contracts.edit', $contract))->assertOk();
});

it('syncs the contract status from the end date through the service', function () {
    $contract = Contract::create(contractPayload([
        'po_number' => 'PO/ITMS/2026/006',
        'start_date' => now()->subMonths(2)->format('Y-m-d'),
        'end_date' => now()->subDay()->format('Y-m-d'),
        'status' => Contract::STATUS_ACTIVE,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]));

    expect(app(ContractService::class)->syncStatuses())->toBeGreaterThanOrEqual(1)
        ->and($contract->fresh()->status)->toBe(Contract::STATUS_INACTIVE);
});
