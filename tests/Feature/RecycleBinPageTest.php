<?php

use App\Models\Contract;
use App\Models\GeneratedDocument;
use App\Models\PurchaseRequest;
use App\Models\RecycleBinItem;
use App\Models\User;
use App\Models\Vendor;
use App\Services\RecycleBinService;
use App\Support\Format;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->service = app(RecycleBinService::class);
});

function recycleVendor(): Vendor
{
    return Vendor::create([
        'company_name' => 'PT Recycle Vendor',
        'company_address' => 'Jl. Uji No. 1',
        'is_active' => true,
    ]);
}

function recycleContract(): Contract
{
    return Contract::create([
        'po_number' => 'PO/RECYCLE/001',
        'contract_date' => '2026-11-01',
        'vendor_id' => recycleVendor()->id,
        'work_name' => 'Pekerjaan Uji Recycle',
        'representative_name' => 'Budi',
        'representative_position' => 'Direktur',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'contract_value' => '1000000',
        'ppn_status' => Contract::PPN_EXCLUDED,
        'payment_method' => Contract::PAYMENT_MONTHLY,
        'monthly_payment_amount' => '100000',
        'status' => Contract::STATUS_ACTIVE,
        'created_by' => test()->user->id,
        'updated_by' => test()->user->id,
    ]);
}

function recyclePr(string $number = 'PR-000001'): PurchaseRequest
{
    return PurchaseRequest::create([
        'pr_number' => $number,
        'status' => PurchaseRequest::STATUS_DRAFT,
        'created_by' => test()->user->id,
        'updated_by' => test()->user->id,
    ]);
}

function recycleDocument(PurchaseRequest $pr): GeneratedDocument
{
    return GeneratedDocument::create([
        'entity_type' => GeneratedDocument::ENTITY_PURCHASE_REQUEST,
        'entity_id' => $pr->id,
        'version_id' => 1,
        'document_format' => 'PDF',
        'document_kind' => 'PR',
        'original_filename' => 'PR.pdf',
        'storage_path' => 'documents/private/pr.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 10,
        'checksum_sha256' => str_repeat('a', 64),
        'generated_by' => test()->user->id,
        'generated_at' => now(),
    ]);
}

it('redirects guests from the recycle bin page', function () {
    $this->get(route('recycle-bin.index'))->assertRedirect(route('login'));
});

it('lists deleted records with their module, number, and expiry', function () {
    $pr = recyclePr();
    $this->service->register('purchase_request', $pr, $this->user);

    $this->actingAs($this->user)
        ->get(route('recycle-bin.index'))
        ->assertOk()
        ->assertSee('Recycle Bin')
        ->assertSee('PR-000001')
        ->assertSee('Purchase Requisition')
        ->assertSee(Format::tanggal(now()->addDays(RecycleBinService::RETENTION_DAYS)));
});

it('shows one row per soft deleted entity and filters by module', function () {
    $this->service->register('purchase_request', recyclePr(), $this->user);
    $this->service->register('contract', recycleContract(), $this->user);

    $this->actingAs($this->user)
        ->get(route('recycle-bin.index', ['module' => 'contract']))
        ->assertOk()
        ->assertSee('PO/RECYCLE/001')
        ->assertDontSee('PR-000001');
});

it('restores a deleted record', function () {
    $pr = recyclePr();
    $item = $this->service->register('purchase_request', $pr, $this->user);

    $this->actingAs($this->user)
        ->post(route('recycle-bin.restore', $item))
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($pr->fresh()->trashed())->toBeFalse()
        ->and(RecycleBinItem::count())->toBe(0);
});

it('permanently deletes a record when nothing protects it', function () {
    $pr = recyclePr();
    $item = $this->service->register('purchase_request', $pr, $this->user);

    $this->actingAs($this->user)
        ->delete(route('recycle-bin.destroy', $item))
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(PurchaseRequest::withTrashed()->find($pr->id))->toBeNull()
        ->and(RecycleBinItem::count())->toBe(0);
});

it('keeps a protected record and explains why permanent deletion is blocked', function () {
    $pr = recyclePr();
    recycleDocument($pr);
    $item = $this->service->register('purchase_request', $pr, $this->user);

    $this->actingAs($this->user)
        ->delete(route('recycle-bin.destroy', $item))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(PurchaseRequest::withTrashed()->find($pr->id))->not->toBeNull()
        ->and(RecycleBinItem::find($item->id))->not->toBeNull();
});
