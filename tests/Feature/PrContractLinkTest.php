<?php

use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Lhstp;
use App\Models\PurchaseRequest;
use App\Models\RecycleBinItem;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ContractService;
use App\Services\PurchaseRequestService;
use App\Services\RecycleBinService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
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
        'subject' => 'Pengadaan Laptop Tim IT',
        'pr_date' => '2026-11-17',
        'category' => 'Fixed Asset',
        'items' => [
            ['budget_name' => 'Laptop 14 inci', 'quantity' => '2', 'unit_price' => '8500000'],
        ],
    ], $this->user);

    $this->contracts = app(ContractService::class);
});

function contractData(array $overrides = []): array
{
    return array_replace([
        'po_number' => 'PO/ITMS/2026/201',
        'contract_date' => '2026-11-17',
        'vendor_id' => test()->vendor->id,
        'work_name' => 'Pengadaan Jaringan Kantor',
        'representative_name' => 'Budi Santoso',
        'representative_position' => 'Direktur',
        'start_date' => '2026-11-01',
        'end_date' => '2027-11-01',
        'contract_value' => '120000000',
        'ppn_status' => Contract::PPN_EXCLUDED,
        'payment_method' => Contract::PAYMENT_MONTHLY,
        'monthly_payment_amount' => '10000000',
    ], $overrides);
}

function linkedContract(): Contract
{
    return test()->contracts->create(
        contractData(['purchase_request_id' => test()->purchaseRequest->id]),
        test()->user,
    );
}

it('links a contract to a PR from the contract form', function () {
    $this->actingAs($this->user)
        ->post(route('contracts.store'), contractData(['purchase_request_id' => $this->purchaseRequest->id]))
        ->assertRedirect();

    $contract = Contract::firstOrFail();

    expect($contract->purchase_request_id)->toBe($this->purchaseRequest->id)
        ->and($contract->purchaseRequest->is($this->purchaseRequest))->toBeTrue()
        ->and($this->purchaseRequest->contracts()->pluck('id')->all())->toBe([$contract->id]);
});

it('allows many contracts on one PR and one PR per contract', function () {
    $first = linkedContract();
    $second = $this->contracts->create(
        contractData(['po_number' => 'PO/ITMS/2026/202', 'purchase_request_id' => $this->purchaseRequest->id]),
        $this->user,
    );

    expect($this->purchaseRequest->contracts()->count())->toBe(2);

    $other = app(PurchaseRequestService::class)->create([
        'recipient_department_id' => Department::where('name', 'Accounting Department')->value('id'),
        'subject' => 'Pengadaan Meja',
        'pr_date' => '2026-11-17',
        'category' => 'Office Supplies',
        'items' => [
            ['budget_name' => 'Meja kerja', 'quantity' => '1', 'unit_price' => '1500000'],
        ],
    ], $this->user);

    $this->contracts->update($second, contractData([
        'po_number' => 'PO/ITMS/2026/202',
        'purchase_request_id' => $other->id,
    ]), $this->user);

    expect($second->fresh()->purchase_request_id)->toBe($other->id)
        ->and($first->fresh()->purchase_request_id)->toBe($this->purchaseRequest->id);
});

it('keeps a contract without a PR valid', function () {
    $contract = $this->contracts->create(contractData(), $this->user);

    expect($contract->purchase_request_id)->toBeNull()
        ->and($contract->purchaseRequest)->toBeNull();
});

it('keeps PO number uniqueness when a PR is linked', function () {
    $this->contracts->create(contractData(['purchase_request_id' => $this->purchaseRequest->id]), $this->user);

    expect(fn () => $this->contracts->create(
        contractData(['purchase_request_id' => $this->purchaseRequest->id]),
        $this->user,
    ))->toThrow(ValidationException::class);
});

it('unlinks a contract and records the action in activity history', function () {
    $contract = linkedContract();

    $this->contracts->unlinkPurchaseRequest($contract, $this->user);

    expect($contract->fresh()->purchase_request_id)->toBeNull()
        ->and($contract->fresh()->work_name)->toBe('Pengadaan Jaringan Kantor');

    $this->assertDatabaseHas('activity_logs', [
        'entity_type' => 'contract',
        'entity_id' => $contract->id,
        'action' => 'unlinked',
    ]);
    $this->assertDatabaseHas('contract_activity_logs', [
        'contract_id' => $contract->id,
        'action' => 'unlinked',
    ]);
});

it('blocks unlinking when the contract has LHSTP records', function () {
    $contract = linkedContract();

    Lhstp::create([
        'lhstp_number' => 'LHSTP/ITMS/2026/201',
        'contract_id' => $contract->id,
        'status' => Lhstp::STATUS_DRAFT,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]);

    $this->contracts->unlinkPurchaseRequest($contract, $this->user);
})->throws(ValidationException::class);

it('blocks clearing the PR through the contract edit form when LHSTP records exist', function () {
    $contract = linkedContract();

    Lhstp::create([
        'lhstp_number' => 'LHSTP/ITMS/2026/202',
        'contract_id' => $contract->id,
        'status' => Lhstp::STATUS_DRAFT,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]);

    $this->contracts->update($contract, contractData(), $this->user);
})->throws(ValidationException::class);

it('shows the linked PR on the contract page and linked contracts on the PR page', function () {
    $contract = linkedContract();

    $this->actingAs($this->user)
        ->get(route('contracts.show', $contract))
        ->assertOk()
        ->assertSee($this->purchaseRequest->pr_number);

    $this->actingAs($this->user)
        ->get(route('purchase-requests.show', $this->purchaseRequest))
        ->assertOk()
        ->assertSee($contract->po_number);
});

it('blocks permanent PR deletion while a linked contract exists', function () {
    $contract = linkedContract();

    app(PurchaseRequestService::class)->deleteDraft($this->purchaseRequest, $this->user);
    $item = RecycleBinItem::where('entity_type', 'purchase_request')
        ->where('entity_id', $this->purchaseRequest->id)
        ->firstOrFail();

    $reason = app(RecycleBinService::class)->purgeItem($item);

    expect($reason)->not->toBeNull()
        ->and(PurchaseRequest::withTrashed()->find($this->purchaseRequest->id))->not->toBeNull()
        ->and(Contract::find($contract->id))->not->toBeNull();
});

it('allows permanent PR deletion after all contracts are unlinked without cascading', function () {
    $contract = linkedContract();
    $this->contracts->unlinkPurchaseRequest($contract, $this->user);

    app(PurchaseRequestService::class)->deleteDraft($this->purchaseRequest, $this->user);
    $item = RecycleBinItem::where('entity_type', 'purchase_request')
        ->where('entity_id', $this->purchaseRequest->id)
        ->firstOrFail();

    expect(app(RecycleBinService::class)->purgeItem($item))->toBeNull()
        ->and(PurchaseRequest::withTrashed()->find($this->purchaseRequest->id))->toBeNull()
        ->and(Contract::find($contract->id))->not->toBeNull();

    expect(ActivityLog::where('entity_type', 'contract')->where('action', 'unlinked')->count())->toBe(1);
});
