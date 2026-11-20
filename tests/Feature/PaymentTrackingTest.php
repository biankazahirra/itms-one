<?php

use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Lhstp;
use App\Models\PaymentEvidence;
use App\Models\PaymentRecord;
use App\Models\RecycleBinItem;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ContractService;
use App\Services\LhstpService;
use App\Services\PaymentStatusCalculator;
use App\Services\PurchaseRequestService;
use App\Services\RecycleBinService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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

    $this->contract = p13Contract();
    $this->calculator = app(PaymentStatusCalculator::class);
});

function p13Contract(array $overrides = []): Contract
{
    return Contract::create(array_replace([
        'po_number' => 'PO/ITMS/2026/301',
        'contract_date' => '2026-11-01',
        'vendor_id' => test()->vendor->id,
        'work_name' => 'Pengadaan Jaringan Kantor',
        'representative_name' => 'Budi Santoso',
        'representative_position' => 'Direktur',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'contract_value' => '500000000',
        'ppn_status' => Contract::PPN_EXCLUDED,
        'payment_method' => Contract::PAYMENT_MONTHLY,
        'monthly_payment_amount' => '10000000',
        'status' => Contract::STATUS_ACTIVE,
        'created_by' => test()->user->id,
        'updated_by' => test()->user->id,
    ], $overrides));
}

function p13LhstpPayload(Contract $contract, array $overrides = []): array
{
    return array_replace([
        'contract_id' => $contract->id,
        'lhstp_number' => 'LHSTP/ITMS/2026/101',
        'lhstp_date' => '2026-11-17',
        'work_period' => 'November 2026',
        'realization_start' => '2026-11-01',
        'realization_end' => '2026-11-05',
        'reference_document_type' => Lhstp::REFERENCE_BERITA_ACARA,
        'reference_document_number' => 'BA/ITMS/2026/101',
        'reference_document_date' => '2026-11-06',
        'payment_amount' => '10000000',
        'ppn_rate' => '12',
        'work_conclusion' => 'Hasil pekerjaan dapat diterima sebagai Penyelesaian pekerjaan periode November 2026.',
        'payment_terms' => 'Berdasarkan ketentuan Purchase Order, pembayaran dilakukan per bulan.',
        'cost_center' => Lhstp::DEFAULT_COST_CENTER,
        'confirm_exceed' => '1',
    ], $overrides);
}

function p13FinalLhstp(Contract $contract, array $overrides = []): Lhstp
{
    $service = app(LhstpService::class);
    $lhstp = $service->create(p13LhstpPayload($contract, $overrides), test()->user);
    $service->finalize($lhstp, test()->user);

    return $lhstp->fresh();
}

function p13StorePayment(Contract $contract, string $amount, array $overrides = [])
{
    return test()->actingAs(test()->user)->post(
        route('contracts.payments.store', $contract),
        array_replace([
            'payment_date' => '2026-11-18',
            'payment_amount' => $amount,
            'payment_reference' => 'TRF/2026/001',
        ], $overrides),
    );
}

it('records a Draft payment that is excluded from paid totals', function () {
    p13FinalLhstp($this->contract);

    p13StorePayment($this->contract, '11100000')->assertRedirect();

    $payment = PaymentRecord::firstOrFail();

    expect($payment->status)->toBe(PaymentRecord::STATUS_DRAFT)
        ->and($payment->payment_amount)->toBe('11100000.00')
        ->and($this->calculator->confirmedTotal($this->contract))->toBe('0')
        ->and($this->calculator->status($this->contract))->toBe(PaymentStatusCalculator::UNPAID)
        ->and(ActivityLog::where('action', 'payment_recorded')->count())->toBe(1);
});

it('counts a confirmed payment and reports partially paid', function () {
    p13FinalLhstp($this->contract);
    p13StorePayment($this->contract, '5000000');
    $payment = PaymentRecord::firstOrFail();

    $this->actingAs($this->user)
        ->post(route('contracts.payments.confirm', [$this->contract, $payment]))
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe(PaymentRecord::STATUS_CONFIRMED)
        ->and($this->calculator->confirmedTotal($this->contract))->toBe('5000000.00')
        ->and($this->calculator->status($this->contract))->toBe(PaymentStatusCalculator::PARTIALLY_PAID)
        ->and(ActivityLog::where('action', 'payment_confirmed')->count())->toBe(1);
});

it('marks the contract paid when confirmed payments cover the obligation', function () {
    p13FinalLhstp($this->contract);

    foreach (['5000000', '6100000'] as $amount) {
        p13StorePayment($this->contract, $amount);
        $payment = PaymentRecord::where('status', PaymentRecord::STATUS_DRAFT)->latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('contracts.payments.confirm', [$this->contract, $payment]));
    }

    expect($this->calculator->obligation($this->contract))->toBe('11100000.00')
        ->and($this->calculator->confirmedTotal($this->contract))->toBe('11100000.00')
        ->and($this->calculator->status($this->contract))->toBe(PaymentStatusCalculator::PAID)
        ->and($this->calculator->outstanding($this->contract))->toBe('0.00');
});

it('rejects an overpayment at confirmation and keeps the record Draft', function () {
    p13FinalLhstp($this->contract);
    p13StorePayment($this->contract, '11100000.01');
    $payment = PaymentRecord::firstOrFail();

    $this->actingAs($this->user)
        ->post(route('contracts.payments.confirm', [$this->contract, $payment]))
        ->assertSessionHasErrors('payment_amount');

    expect($payment->fresh()->status)->toBe(PaymentRecord::STATUS_DRAFT)
        ->and($this->calculator->confirmedTotal($this->contract))->toBe('0');
});

it('derives the obligation from Final LHSTP amounts only', function () {
    $service = app(LhstpService::class);
    $lhstp = $service->create(p13LhstpPayload($this->contract), $this->user);

    expect($this->calculator->obligation($this->contract))->toBe('0.00');

    $service->finalize($lhstp, $this->user);
    expect($this->calculator->obligation($this->contract))->toBe('11100000.00');

    $service->archive($lhstp->fresh(), $this->user);
    expect($this->calculator->obligation($this->contract))->toBe('0');
});

it('blocks deleting a Confirmed record', function () {
    p13FinalLhstp($this->contract);
    p13StorePayment($this->contract, '1000000');
    $payment = PaymentRecord::firstOrFail();
    $this->actingAs($this->user)->post(route('contracts.payments.confirm', [$this->contract, $payment]));

    $this->actingAs($this->user)
        ->delete(route('contracts.payments.destroy', [$this->contract, $payment]))
        ->assertSessionHasErrors('status');

    expect(PaymentRecord::find($payment->id))->not->toBeNull();
});

it('appends a correction entry and preserves the original record', function () {
    p13FinalLhstp($this->contract);
    p13StorePayment($this->contract, '5000000');
    $original = PaymentRecord::firstOrFail();
    $this->actingAs($this->user)->post(route('contracts.payments.confirm', [$this->contract, $original]));

    $this->actingAs($this->user)
        ->post(route('contracts.payments.correct', [$this->contract, $original]), [
            'payment_date' => '2026-11-19',
            'payment_amount' => '-2000000',
            'payment_reference' => 'KOREKSI/2026/001',
        ])
        ->assertRedirect();

    $correction = PaymentRecord::whereNotNull('corrects_payment_record_id')->firstOrFail();
    $this->actingAs($this->user)->post(route('contracts.payments.confirm', [$this->contract, $correction]));

    expect($correction->corrects_payment_record_id)->toBe($original->id)
        ->and($original->fresh()->status)->toBe(PaymentRecord::STATUS_CONFIRMED)
        ->and($original->fresh()->payment_amount)->toBe('5000000.00')
        ->and($this->calculator->confirmedTotal($this->contract))->toBe('3000000.00')
        ->and(ActivityLog::where('action', 'payment_corrected')->count())->toBe(1);
});

it('rejects a zero correction and a correction on a Draft record', function () {
    p13FinalLhstp($this->contract);
    p13StorePayment($this->contract, '5000000');
    $draft = PaymentRecord::firstOrFail();

    $this->actingAs($this->user)
        ->post(route('contracts.payments.correct', [$this->contract, $draft]), [
            'payment_date' => '2026-11-19',
            'payment_amount' => '-1000',
            'payment_reference' => 'KOREKSI/2026/002',
        ])
        ->assertSessionHasErrors('status');

    $this->actingAs($this->user)->post(route('contracts.payments.confirm', [$this->contract, $draft]));

    $this->actingAs($this->user)
        ->post(route('contracts.payments.correct', [$this->contract, $draft]), [
            'payment_date' => '2026-11-19',
            'payment_amount' => '0',
            'payment_reference' => 'KOREKSI/2026/003',
        ])
        ->assertSessionHasErrors('payment_amount');

    expect(PaymentRecord::whereNotNull('corrects_payment_record_id')->count())->toBe(0);
});

it('validates payment evidence formats and size', function () {
    p13StorePayment($this->contract, '5000000');
    $payment = PaymentRecord::firstOrFail();

    $this->actingAs($this->user)
        ->post(route('contracts.payments.evidences.store', [$this->contract, $payment]), [
            'file' => UploadedFile::fake()->create('bukti.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->assertSessionHasErrors('file');

    $this->actingAs($this->user)
        ->post(route('contracts.payments.evidences.store', [$this->contract, $payment]), [
            'file' => UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf'),
        ])
        ->assertSessionHasErrors('file');

    expect(PaymentEvidence::count())->toBe(0);
});

it('stores multiple evidences and restores a deleted one from the recycle bin', function () {
    p13StorePayment($this->contract, '5000000');
    $payment = PaymentRecord::firstOrFail();

    foreach (['bukti-1.pdf', 'bukti-2.jpg'] as $name) {
        $this->actingAs($this->user)
            ->post(route('contracts.payments.evidences.store', [$this->contract, $payment]), [
                'file' => UploadedFile::fake()->create($name, 120, str_ends_with($name, '.pdf') ? 'application/pdf' : 'image/jpeg'),
            ])
            ->assertRedirect();
    }

    expect($payment->evidences()->count())->toBe(2);

    $evidence = $payment->evidences()->firstOrFail();

    $this->actingAs($this->user)
        ->delete(route('contracts.payments.evidences.destroy', [$this->contract, $payment, $evidence]))
        ->assertRedirect();

    expect(PaymentEvidence::withTrashed()->find($evidence->id)->trashed())->toBeTrue()
        ->and(RecycleBinItem::where('entity_type', 'payment_evidence')->where('entity_id', $evidence->id)->exists())->toBeTrue();

    $item = RecycleBinItem::where('entity_type', 'payment_evidence')->where('entity_id', $evidence->id)->firstOrFail();
    app(RecycleBinService::class)->restore($item);

    expect(PaymentEvidence::find($evidence->id))->not->toBeNull();
    Storage::disk('local')->assertExists($evidence->storage_path);
});

it('purges evidence metadata and the physical file', function () {
    p13StorePayment($this->contract, '5000000');
    $payment = PaymentRecord::firstOrFail();

    $this->actingAs($this->user)->post(route('contracts.payments.evidences.store', [$this->contract, $payment]), [
        'file' => UploadedFile::fake()->create('bukti.pdf', 120, 'application/pdf'),
    ]);

    $evidence = PaymentEvidence::firstOrFail();

    $this->actingAs($this->user)
        ->delete(route('contracts.payments.evidences.destroy', [$this->contract, $payment, $evidence]));

    $item = RecycleBinItem::where('entity_type', 'payment_evidence')->where('entity_id', $evidence->id)->firstOrFail();

    expect(app(RecycleBinService::class)->purgeItem($item))->toBeNull()
        ->and(PaymentEvidence::withTrashed()->find($evidence->id))->toBeNull();

    Storage::disk('local')->assertMissing($evidence->storage_path);
});

it('blocks finalizing a revision that would create an overpayment', function () {
    $lhstp = p13FinalLhstp($this->contract);
    p13StorePayment($this->contract, '11100000');
    $payment = PaymentRecord::firstOrFail();
    $this->actingAs($this->user)->post(route('contracts.payments.confirm', [$this->contract, $payment]));

    $service = app(LhstpService::class);
    $revision = $service->createRevision($lhstp->fresh(), $this->user);

    $service->updateDraft($lhstp->fresh(), p13LhstpPayload($this->contract, [
        'payment_amount' => '5000000',
    ]), $this->user);

    expect(fn () => $service->finalize($lhstp->fresh(), $this->user))
        ->toThrow(ValidationException::class);

    expect($lhstp->fresh()->status)->toBe(Lhstp::STATUS_DRAFT)
        ->and($revision->fresh()->finalized_at)->toBeNull();
});

it('blocks archiving a Final LHSTP below the confirmed payments', function () {
    $first = p13FinalLhstp($this->contract, [
        'lhstp_number' => 'LHSTP/ITMS/2026/111',
        'reference_document_number' => 'BA/ITMS/2026/111',
    ]);
    $second = p13FinalLhstp($this->contract, [
        'lhstp_number' => 'LHSTP/ITMS/2026/112',
        'reference_document_number' => 'BA/ITMS/2026/112',
        'realization_start' => '2026-11-10',
        'realization_end' => '2026-11-15',
        'reference_document_date' => '2026-11-16',
    ]);

    expect($this->calculator->obligation($this->contract))->toBe('22200000.00');

    p13StorePayment($this->contract, '20000000');
    $payment = PaymentRecord::firstOrFail();
    $this->actingAs($this->user)->post(route('contracts.payments.confirm', [$this->contract, $payment]));

    expect(fn () => app(LhstpService::class)->archive($first->fresh(), $this->user))
        ->toThrow(ValidationException::class);

    expect($first->fresh()->status)->toBe(Lhstp::STATUS_FINAL)
        ->and($second->fresh()->status)->toBe(Lhstp::STATUS_FINAL);
});

it('blocks unlinking a contract that has payment records', function () {
    $purchaseRequest = app(PurchaseRequestService::class)->create([
        'recipient_department_id' => Department::where('name', 'Accounting Department')->value('id'),
        'subject' => 'Pengadaan Jaringan',
        'pr_date' => '2026-11-17',
        'category' => 'Fixed Asset',
        'items' => [
            ['budget_name' => 'Switch jaringan', 'quantity' => '1', 'unit_price' => '10000000'],
        ],
    ], $this->user);

    $contract = app(ContractService::class)->create([
        'po_number' => 'PO/ITMS/2026/302',
        'contract_date' => '2026-11-17',
        'vendor_id' => $this->vendor->id,
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
    ], $this->user);

    p13StorePayment($contract, '1000000');

    $this->actingAs($this->user)
        ->post(route('contracts.unlink', $contract))
        ->assertSessionHasErrors('purchase_request_id');

    expect($contract->fresh()->purchase_request_id)->toBe($purchaseRequest->id);
});

it('shows the derived payment summary on the PR detail page', function () {
    $purchaseRequest = app(PurchaseRequestService::class)->create([
        'recipient_department_id' => Department::where('name', 'Accounting Department')->value('id'),
        'subject' => 'Pengadaan Jaringan',
        'pr_date' => '2026-11-17',
        'category' => 'Fixed Asset',
        'items' => [
            ['budget_name' => 'Switch jaringan', 'quantity' => '1', 'unit_price' => '10000000'],
        ],
    ], $this->user);

    $contract = p13Contract([
        'po_number' => 'PO/ITMS/2026/303',
        'purchase_request_id' => $purchaseRequest->id,
    ]);

    p13FinalLhstp($contract);
    p13StorePayment($contract, '5000000');
    $payment = PaymentRecord::firstOrFail();
    $this->actingAs($this->user)->post(route('contracts.payments.confirm', [$contract, $payment]));

    $this->actingAs($this->user)
        ->get(route('purchase-requests.show', $purchaseRequest))
        ->assertOk()
        ->assertSee('Ringkasan Pembayaran')
        ->assertSee('Partially Paid');
});

it('renders payment records and the derived status on the contract page', function () {
    p13FinalLhstp($this->contract);
    p13StorePayment($this->contract, '5000000', ['payment_reference' => 'TRF/2026/999']);
    $payment = PaymentRecord::firstOrFail();
    $this->actingAs($this->user)->post(route('contracts.payments.confirm', [$this->contract, $payment]));

    $this->actingAs($this->user)
        ->get(route('contracts.show', $this->contract))
        ->assertOk()
        ->assertSee('TRF/2026/999')
        ->assertSee('Partially Paid');
});

it('blocks purging a contract that still has payment records', function () {
    p13StorePayment($this->contract, '1000000');

    app(RecycleBinService::class)->register('contract', $this->contract, $this->user);
    $item = RecycleBinItem::where('entity_type', 'contract')
        ->where('entity_id', $this->contract->id)
        ->firstOrFail();

    expect(app(RecycleBinService::class)->purgeItem($item))->not->toBeNull()
        ->and(Contract::withTrashed()->find($this->contract->id))->not->toBeNull();
});
