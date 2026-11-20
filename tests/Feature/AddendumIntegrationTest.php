<?php

use App\Models\Contract;
use App\Models\Department;
use App\Models\Lhstp;
use App\Models\PaymentEvidence;
use App\Models\PaymentRecord;
use App\Models\PrDocument;
use App\Models\PurchaseRequest;
use App\Models\RecycleBinItem;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ContractService;
use App\Services\LhstpService;
use App\Services\PrTrackingService;
use App\Services\PurchaseRequestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create();

    $this->vendor = Vendor::create([
        'company_name' => 'PT Mitra Teknologi',
        'company_address' => 'Jl. Industri No. 1, Cilegon',
        'is_active' => true,
    ]);

    Department::create(['name' => 'Accounting Department']);
    Department::create(['name' => 'ITMS']);

    $this->purchaseRequest = app(PurchaseRequestService::class)->create([
        'recipient_department_id' => Department::where('name', 'Accounting Department')->value('id'),
        'subject' => 'Pengadaan Jaringan Kantor',
        'pr_date' => '2026-11-17',
        'category' => 'Fixed Asset',
        'items' => [
            ['budget_name' => 'Switch jaringan', 'quantity' => '1', 'unit_price' => '10000000'],
        ],
    ], $this->user);
});

function p14Contract(PurchaseRequest $purchaseRequest, array $overrides = []): Contract
{
    return app(ContractService::class)->create(array_replace([
        'po_number' => 'PO/ITMS/2026/401',
        'contract_date' => '2026-11-17',
        'vendor_id' => test()->vendor->id,
        'purchase_request_id' => $purchaseRequest->id,
        'work_name' => 'Pengadaan Jaringan Kantor',
        'representative_name' => 'Budi Santoso',
        'representative_position' => 'Direktur',
        'start_date' => '2026-11-01',
        'end_date' => '2027-11-01',
        'contract_value' => '120000000',
        'ppn_status' => Contract::PPN_EXCLUDED,
        'payment_method' => Contract::PAYMENT_MONTHLY,
        'monthly_payment_amount' => '10000000',
    ], $overrides), test()->user);
}

function p14PaidContract(PurchaseRequest $purchaseRequest): Contract
{
    $contract = p14Contract($purchaseRequest);

    $service = app(LhstpService::class);
    $lhstp = $service->create([
        'contract_id' => $contract->id,
        'lhstp_number' => 'LHSTP/ITMS/2026/401',
        'lhstp_date' => '2026-11-17',
        'work_period' => 'November 2026',
        'realization_start' => '2026-11-01',
        'realization_end' => '2026-11-05',
        'reference_document_type' => Lhstp::REFERENCE_BERITA_ACARA,
        'reference_document_number' => 'BA/ITMS/2026/401',
        'reference_document_date' => '2026-11-06',
        'payment_amount' => '10000000',
        'ppn_rate' => '12',
        'work_conclusion' => 'Hasil pekerjaan dapat diterima.',
        'payment_terms' => 'Pembayaran dilakukan per bulan.',
        'cost_center' => Lhstp::DEFAULT_COST_CENTER,
        'confirm_exceed' => '1',
    ], test()->user);
    $service->finalize($lhstp, test()->user);

    test()->actingAs(test()->user)->post(route('contracts.payments.store', $contract), [
        'payment_date' => '2026-11-18',
        'payment_amount' => '5000000',
        'payment_reference' => 'TRF/2026/401',
    ]);

    $payment = PaymentRecord::firstOrFail();
    test()->actingAs(test()->user)->post(route('contracts.payments.confirm', [$contract, $payment]));

    return $contract;
}

it('shows and filters the tracking status on the PR list', function () {
    $tracking = app(PrTrackingService::class);

    $this->actingAs($this->user)
        ->get(route('purchase-requests.index'))
        ->assertOk()
        ->assertSee('Not Tracked')
        ->assertSee($this->purchaseRequest->pr_number);

    $this->actingAs($this->user)
        ->get(route('purchase-requests.index', ['tracking' => PrTrackingService::STATUS_DONE]))
        ->assertOk()
        ->assertDontSee($this->purchaseRequest->pr_number);

    $tracking->record($this->purchaseRequest, PrTrackingService::STAGES[0], $this->user);

    $this->actingAs($this->user)
        ->get(route('purchase-requests.index', ['tracking' => PrTrackingService::STATUS_NOT_TRACKED]))
        ->assertOk()
        ->assertDontSee($this->purchaseRequest->pr_number);

    $this->actingAs($this->user)
        ->get(route('purchase-requests.index', ['tracking' => PrTrackingService::STATUS_IN_PROGRESS]))
        ->assertOk()
        ->assertSee($this->purchaseRequest->pr_number);
});

it('appends stage updates from the PR detail timeline', function () {
    $this->actingAs($this->user)
        ->get(route('purchase-requests.show', $this->purchaseRequest))
        ->assertOk()
        ->assertSee('Tracking PR')
        ->assertSee(PrTrackingService::STAGES[0]);

    $this->actingAs($this->user)
        ->post(route('purchase-requests.stage-updates.store', $this->purchaseRequest), [
            'stage' => PrTrackingService::STAGES[0],
            'note' => 'Diterima HC',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('pr_stage_updates', [
        'purchase_request_id' => $this->purchaseRequest->id,
        'stage' => PrTrackingService::STAGES[0],
        'note' => 'Diterima HC',
    ]);

    $this->actingAs($this->user)
        ->get(route('purchase-requests.show', $this->purchaseRequest))
        ->assertOk()
        ->assertSee('Diterima HC')
        ->assertSee('In Progress');
});

it('rejects a tracking stage that skips ahead', function () {
    $this->actingAs($this->user)
        ->post(route('purchase-requests.stage-updates.store', $this->purchaseRequest), [
            'stage' => PrTrackingService::STAGES[4],
        ])
        ->assertSessionHasErrors('stage');

    expect($this->purchaseRequest->stageUpdates()->count())->toBe(0);
});

it('links PR, contract, payments and the archive across module pages', function () {
    $contract = p14PaidContract($this->purchaseRequest);

    $this->actingAs($this->user)
        ->get(route('purchase-requests.show', $this->purchaseRequest))
        ->assertOk()
        ->assertSee('Tracking PR')
        ->assertSee('Arsip Dokumen PR')
        ->assertSee(route('contracts.show', $contract))
        ->assertSee('Ringkasan Pembayaran')
        ->assertSee('Partially Paid');

    $this->actingAs($this->user)
        ->get(route('contracts.show', $contract))
        ->assertOk()
        ->assertSee(route('purchase-requests.show', $this->purchaseRequest))
        ->assertSee('TRF/2026/401')
        ->assertSee('Pembayaran');
});

it('renders a back link to the module list on detail pages', function () {
    $contract = p14Contract($this->purchaseRequest);

    $this->actingAs($this->user)
        ->get(route('contracts.show', $contract))
        ->assertOk()
        ->assertSee('Kembali')
        ->assertSee(route('contracts.index'));

    $this->actingAs($this->user)
        ->get(route('purchase-requests.show', $this->purchaseRequest))
        ->assertOk()
        ->assertSee('Kembali')
        ->assertSee(route('purchase-requests.index'));
});

it('lists both new recycle bin modules and restores them through the page', function () {
    $this->actingAs($this->user)->post(route('purchase-requests.pr-documents.store', $this->purchaseRequest), [
        'category' => PrDocument::CATEGORIES[2],
        'file' => UploadedFile::fake()->create('pr-final.pdf', 120, 'application/pdf'),
    ]);

    $document = PrDocument::firstOrFail();

    $this->actingAs($this->user)
        ->delete(route('purchase-requests.pr-documents.destroy', [$this->purchaseRequest, $document]));

    $contract = p14Contract($this->purchaseRequest);
    $this->actingAs($this->user)->post(route('contracts.payments.store', $contract), [
        'payment_date' => '2026-11-18',
        'payment_amount' => '1000000',
        'payment_reference' => 'TRF/2026/402',
    ]);

    $payment = PaymentRecord::firstOrFail();

    $this->actingAs($this->user)->post(route('contracts.payments.evidences.store', [$contract, $payment]), [
        'file' => UploadedFile::fake()->create('bukti.pdf', 120, 'application/pdf'),
    ]);

    $evidence = PaymentEvidence::firstOrFail();

    $this->actingAs($this->user)
        ->delete(route('contracts.payments.evidences.destroy', [$contract, $payment, $evidence]));

    $this->actingAs($this->user)
        ->get(route('recycle-bin.index'))
        ->assertOk()
        ->assertSee('Dokumen PR')
        ->assertSee('Bukti Pembayaran')
        ->assertSee('pr-final.pdf')
        ->assertSee('bukti.pdf');

    $documentItem = RecycleBinItem::where('entity_type', 'pr_document')->firstOrFail();
    $evidenceItem = RecycleBinItem::where('entity_type', 'payment_evidence')->firstOrFail();

    $this->actingAs($this->user)->post(route('recycle-bin.restore', $documentItem))->assertRedirect();
    $this->actingAs($this->user)->post(route('recycle-bin.restore', $evidenceItem))->assertRedirect();

    expect(PrDocument::find($document->id))->not->toBeNull()
        ->and(PaymentEvidence::find($evidence->id))->not->toBeNull();

    Storage::disk('local')->assertExists($document->storage_path)
        ->assertExists($evidence->storage_path);
});

it('purges both new recycle bin modules through the page', function () {
    $this->actingAs($this->user)->post(route('purchase-requests.pr-documents.store', $this->purchaseRequest), [
        'category' => PrDocument::CATEGORIES[0],
        'file' => UploadedFile::fake()->create('arsip.pdf', 120, 'application/pdf'),
    ]);

    $document = PrDocument::firstOrFail();
    $this->actingAs($this->user)
        ->delete(route('purchase-requests.pr-documents.destroy', [$this->purchaseRequest, $document]));

    $contract = p14Contract($this->purchaseRequest);
    $this->actingAs($this->user)->post(route('contracts.payments.store', $contract), [
        'payment_date' => '2026-11-18',
        'payment_amount' => '1000000',
        'payment_reference' => 'TRF/2026/403',
    ]);

    $payment = PaymentRecord::firstOrFail();

    $this->actingAs($this->user)->post(route('contracts.payments.evidences.store', [$contract, $payment]), [
        'file' => UploadedFile::fake()->create('bukti-purger.pdf', 120, 'application/pdf'),
    ]);

    $evidence = PaymentEvidence::firstOrFail();
    $this->actingAs($this->user)
        ->delete(route('contracts.payments.evidences.destroy', [$contract, $payment, $evidence]));

    foreach (RecycleBinItem::all() as $item) {
        $this->actingAs($this->user)
            ->delete(route('recycle-bin.destroy', $item))
            ->assertRedirect();
    }

    expect(PrDocument::withTrashed()->find($document->id))->toBeNull()
        ->and(PaymentEvidence::withTrashed()->find($evidence->id))->toBeNull();

    Storage::disk('local')->assertMissing($document->storage_path)
        ->assertMissing($evidence->storage_path);
});
