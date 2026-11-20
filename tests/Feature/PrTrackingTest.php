<?php

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\PrTrackingService;
use App\Services\PurchaseRequestService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->user = User::factory()->create();

    Department::create(['name' => 'Accounting Department']);
    Department::create(['name' => 'ITMS']);

    $this->service = app(PrTrackingService::class);

    $this->purchaseRequest = app(PurchaseRequestService::class)->create([
        'recipient_department_id' => Department::where('name', 'Accounting Department')->value('id'),
        'subject' => 'Pengadaan Laptop Tim IT',
        'pr_date' => '2026-11-17',
        'category' => 'Fixed Asset',
        'items' => [
            ['budget_name' => 'Laptop 14 inci', 'quantity' => '2', 'unit_price' => '8500000'],
        ],
    ], $this->user);
});

it('derives Not Tracked when the PR has no stage history', function () {
    expect($this->service->status($this->purchaseRequest))->toBe(PrTrackingService::STATUS_NOT_TRACKED)
        ->and($this->purchaseRequest->stageUpdates()->count())->toBe(0);
});

it('does not start tracking when a PR becomes Final', function () {
    app(PurchaseRequestService::class)->finalize($this->purchaseRequest, $this->user);

    expect($this->service->status($this->purchaseRequest->fresh()))->toBe(PrTrackingService::STATUS_NOT_TRACKED);
});

it('records the five approved stages sequentially and derives In Progress', function () {
    $this->service->record($this->purchaseRequest, PrTrackingService::STAGES[0], $this->user, 'Dikirim ke HC');

    $update = $this->purchaseRequest->stageUpdates()->firstOrFail();

    expect($this->service->status($this->purchaseRequest->fresh()))->toBe(PrTrackingService::STATUS_IN_PROGRESS)
        ->and($update->stage)->toBe('Submission to HC/Accounting')
        ->and($update->note)->toBe('Dikirim ke HC')
        ->and($update->created_by)->toBe($this->user->id)
        ->and($update->occurred_at)->not->toBeNull();
});

it('blocks a stage that skips ahead', function () {
    $this->service->record($this->purchaseRequest, PrTrackingService::STAGES[0], $this->user);

    $this->service->record($this->purchaseRequest, PrTrackingService::STAGES[2], $this->user);
})->throws(ValidationException::class);

it('blocks a stage that moves backwards', function () {
    $this->service->record($this->purchaseRequest, PrTrackingService::STAGES[0], $this->user);
    $this->service->record($this->purchaseRequest, PrTrackingService::STAGES[1], $this->user);

    $this->service->record($this->purchaseRequest, PrTrackingService::STAGES[0], $this->user);
})->throws(ValidationException::class);

it('requires Stage 1 as the first record', function () {
    $this->service->record($this->purchaseRequest, PrTrackingService::STAGES[0], $this->user);

    expect($this->service->status($this->purchaseRequest->fresh()))->toBe(PrTrackingService::STATUS_IN_PROGRESS);

    $other = app(PurchaseRequestService::class)->create([
        'recipient_department_id' => Department::where('name', 'Accounting Department')->value('id'),
        'subject' => 'Pengadaan Meja',
        'pr_date' => '2026-11-17',
        'category' => 'Office Supplies',
        'items' => [
            ['budget_name' => 'Meja kerja', 'quantity' => '1', 'unit_price' => '1500000'],
        ],
    ], $this->user);

    $this->service->record($other, PrTrackingService::STAGES[1], $this->user);
})->throws(ValidationException::class);

it('appends a correction row without overwriting prior history', function () {
    $this->service->record($this->purchaseRequest, PrTrackingService::STAGES[0], $this->user, 'Catatan awal');
    $this->service->record($this->purchaseRequest, PrTrackingService::STAGES[0], $this->user, 'Catatan koreksi');

    $updates = $this->purchaseRequest->stageUpdates()->get();

    expect($updates)->toHaveCount(2)
        ->and($updates[0]->note)->toBe('Catatan awal')
        ->and($updates[1]->note)->toBe('Catatan koreksi');
});

it('derives Done after Stage 5', function () {
    foreach (PrTrackingService::STAGES as $stage) {
        $this->service->record($this->purchaseRequest, $stage, $this->user);
    }

    expect($this->service->status($this->purchaseRequest->fresh()))->toBe(PrTrackingService::STATUS_DONE);
});

it('never changes the document status', function () {
    $this->purchaseRequest->forceFill([
        'status' => PurchaseRequest::STATUS_ARCHIVED,
        'archived_at' => now(),
    ])->save();

    foreach (PrTrackingService::STAGES as $stage) {
        $this->service->record($this->purchaseRequest, $stage, $this->user);
    }

    expect($this->purchaseRequest->fresh()->status)->toBe(PurchaseRequest::STATUS_ARCHIVED);
});

it('records each stage update in the activity history', function () {
    $this->service->record($this->purchaseRequest, PrTrackingService::STAGES[0], $this->user, 'Dikirim ke HC');

    $log = ActivityLog::query()
        ->where('entity_type', 'purchase_request')
        ->where('entity_id', $this->purchaseRequest->id)
        ->where('action', 'stage_updated')
        ->firstOrFail();

    expect($log->description)->toContain('Submission to HC/Accounting');
});

it('does not store a tracking_status column on purchase_requests', function () {
    expect(Schema::hasColumn('purchase_requests', 'tracking_status'))->toBeFalse();
});
