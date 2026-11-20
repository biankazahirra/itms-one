<?php

namespace App\Services;

use App\Models\PrStageUpdate;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrTrackingService
{
    public const STATUS_NOT_TRACKED = 'Not Tracked';

    public const STATUS_IN_PROGRESS = 'In Progress';

    public const STATUS_DONE = 'Done';

    /**
     * The five approved tracking stages, in required order.
     *
     * @var list<string>
     */
    public const STAGES = [
        'Submission to HC/Accounting',
        'ITMS Approval',
        'HC/Accounting Verification',
        'Procurement Submission',
        'Goods/Services Received',
    ];

    public function __construct(private readonly ActivityLogService $activity) {}

    /**
     * Derive the tracking status from the append-only stage history.
     */
    public function status(PurchaseRequest $purchaseRequest): string
    {
        $stage = $this->latestStage($purchaseRequest);

        if ($stage === null) {
            return self::STATUS_NOT_TRACKED;
        }

        return $stage === self::STAGES[4] ? self::STATUS_DONE : self::STATUS_IN_PROGRESS;
    }

    /**
     * Append a stage update. Prior rows are never changed or removed.
     */
    public function record(PurchaseRequest $purchaseRequest, string $stage, User $actor, ?string $note = null): PrStageUpdate
    {
        $index = array_search($stage, self::STAGES, true);

        if ($index === false) {
            throw ValidationException::withMessages([
                'stage' => 'Tahap tracking tidak dikenal.',
            ]);
        }

        return DB::transaction(function () use ($purchaseRequest, $stage, $index, $actor, $note): PrStageUpdate {
            $pr = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);

            $current = $this->latestStage($pr);
            $currentIndex = $current === null ? -1 : array_search($current, self::STAGES, true);

            // Only the next stage or a correction of the current stage may be appended.
            if ($index !== $currentIndex + 1 && $index !== $currentIndex) {
                throw ValidationException::withMessages([
                    'stage' => 'Tahap tracking harus berurutan.',
                ]);
            }

            $update = PrStageUpdate::create([
                'purchase_request_id' => $pr->id,
                'stage' => $stage,
                'note' => $note,
                'occurred_at' => now(),
                'created_by' => $actor->id,
                'created_at' => now(),
            ]);

            $this->activity->record(
                'purchase_request',
                $pr->id,
                'stage_updated',
                "Memperbarui tahap tracking PR {$pr->pr_number}: {$stage}.",
                null,
                ['stage' => $stage, 'note' => $note, 'status' => $this->status($pr)],
                $actor,
            );

            return $update;
        });
    }

    /**
     * Stage currently recorded, or null when the PR is not tracked yet.
     */
    public function currentStage(PurchaseRequest $purchaseRequest): ?string
    {
        return $this->latestStage($purchaseRequest);
    }

    /**
     * Stages that may be appended next: the current stage (correction) and the
     * following stage when it exists.
     *
     * @return list<string>
     */
    public function allowedStages(PurchaseRequest $purchaseRequest): array
    {
        $current = $this->latestStage($purchaseRequest);

        if ($current === null) {
            return [self::STAGES[0]];
        }

        $index = array_search($current, self::STAGES, true);

        return isset(self::STAGES[$index + 1])
            ? [$current, self::STAGES[$index + 1]]
            : [$current];
    }

    private function latestStage(PurchaseRequest $purchaseRequest): ?string
    {
        return $purchaseRequest->stageUpdates()->reorder()->latest('id')->value('stage');
    }
}
