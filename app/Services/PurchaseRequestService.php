<?php

namespace App\Services;

use App\Models\GeneratedDocument;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestVersion;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRequestService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly RecycleBinService $recycleBin,
        private readonly DocumentNumberService $numbers,
        private readonly PurchaseRequestPdfService $pdf,
    ) {}

    /**
     * Create a Draft PR with its first version and items.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): PurchaseRequest
    {
        return DB::transaction(function () use ($data, $actor): PurchaseRequest {
            $purchaseRequest = PurchaseRequest::create([
                'pr_number' => $this->numbers->next('PR'),
                'status' => PurchaseRequest::STATUS_DRAFT,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $version = $this->persistVersion($purchaseRequest, $data, $actor, 1);

            $purchaseRequest->forceFill(['current_version_id' => $version->id])->save();

            $this->activity->record(
                'purchase_request',
                $purchaseRequest->id,
                'created',
                "Membuat PR {$purchaseRequest->pr_number}.",
                null,
                $this->snapshot($purchaseRequest->fresh('currentVersion')),
                $actor,
            );

            return $purchaseRequest;
        });
    }

    /**
     * Replace the header and items of the current Draft version.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(PurchaseRequest $purchaseRequest, array $data, User $actor): void
    {
        DB::transaction(function () use ($purchaseRequest, $data, $actor): void {
            $pr = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);

            if (! $pr->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya PR berstatus Draft yang dapat diubah.',
                ]);
            }

            $version = $pr->currentVersion;

            if ($version === null || $version->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => 'Versi PR ini sudah final dan tidak dapat diubah.',
                ]);
            }

            $before = $this->snapshot($pr);

            $items = $this->buildItems($data['items']);

            $version->forceFill([
                'recipient_department_id' => $data['recipient_department_id'],
                'subject' => $data['subject'],
                'pr_date' => $data['pr_date'],
                'category' => $data['category'] ?? null,
                'grand_total' => Money::sum(array_column($items, 'estimated_total')),
            ])->save();

            $version->items()->delete();
            $version->items()->createMany($items);

            $pr->forceFill(['updated_by' => $actor->id])->save();

            $this->activity->record(
                'purchase_request',
                $pr->id,
                'updated',
                "Memperbarui draft PR {$pr->pr_number}.",
                $before,
                $this->snapshot($pr->fresh('currentVersion')),
                $actor,
            );
        });
    }

    /**
     * Finalize the current Draft version. Finalized versions are immutable.
     */
    public function finalize(PurchaseRequest $purchaseRequest, User $actor, bool $generateDocument = false): ?GeneratedDocument
    {
        return DB::transaction(function () use ($purchaseRequest, $actor, $generateDocument): ?GeneratedDocument {
            $pr = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);

            if (! $pr->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya PR berstatus Draft yang dapat difinalisasi.',
                ]);
            }

            $version = $pr->currentVersion;

            if ($version === null || $version->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => 'Versi PR ini sudah final.',
                ]);
            }

            $items = $version->items()->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'PR harus memiliki minimal satu baris anggaran sebelum difinalisasi.',
                ]);
            }

            $grandTotal = Money::sum($items->pluck('estimated_total')->all());

            $version->forceFill([
                'grand_total' => $grandTotal,
                'finalized_by' => $actor->id,
                'finalized_at' => now(),
            ])->save();

            $pr->forceFill([
                'status' => PurchaseRequest::STATUS_FINAL,
                'finalized_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            $this->activity->record(
                'purchase_request',
                $pr->id,
                'finalized',
                "Memfinalisasi PR {$pr->pr_number} versi {$version->label()}.",
                null,
                $this->snapshot($pr->fresh('currentVersion')),
                $actor,
            );

            return $generateDocument ? $this->pdf->generate($pr, $version, $actor) : null;
        });
    }

    /**
     * Create the next Draft version from the current Final version.
     */
    public function createRevision(PurchaseRequest $purchaseRequest, User $actor): PurchaseRequestVersion
    {
        return DB::transaction(function () use ($purchaseRequest, $actor): PurchaseRequestVersion {
            $pr = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);

            if ($pr->status !== PurchaseRequest::STATUS_FINAL) {
                throw ValidationException::withMessages([
                    'status' => 'Revisi hanya dapat dibuat dari PR berstatus Final.',
                ]);
            }

            $source = $pr->currentVersion;

            if ($source === null || ! $source->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => 'Versi final PR tidak ditemukan.',
                ]);
            }

            $nextNumber = ((int) $pr->versions()->max('version_number')) + 1;

            $version = $pr->versions()->create([
                'version_number' => $nextNumber,
                'recipient_department_id' => $source->recipient_department_id,
                'subject' => $source->subject,
                'pr_date' => $source->pr_date,
                'category' => $source->category,
                'grand_total' => $source->grand_total,
                'created_by' => $actor->id,
            ]);

            foreach ($source->items()->get() as $item) {
                $version->items()->create([
                    'budget_name' => $item->budget_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'estimated_total' => $item->estimated_total,
                    'sort_order' => $item->sort_order,
                ]);
            }

            $pr->forceFill([
                'status' => PurchaseRequest::STATUS_DRAFT,
                'current_version_id' => $version->id,
                'archived_at' => null,
                'finalized_at' => null,
                'updated_by' => $actor->id,
            ])->save();

            $this->activity->record(
                'purchase_request',
                $pr->id,
                'revision_created',
                "Membuat revisi {$version->label()} untuk PR {$pr->pr_number}.",
                null,
                $this->snapshot($pr->fresh('currentVersion')),
                $actor,
            );

            return $version;
        });
    }

    public function archive(PurchaseRequest $purchaseRequest, User $actor): void
    {
        DB::transaction(function () use ($purchaseRequest, $actor): void {
            $pr = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);

            if ($pr->status !== PurchaseRequest::STATUS_FINAL) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya PR berstatus Final yang dapat diarsipkan.',
                ]);
            }

            $pr->forceFill([
                'status' => PurchaseRequest::STATUS_ARCHIVED,
                'archived_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            $this->activity->record(
                'purchase_request',
                $pr->id,
                'archived',
                "Mengarsipkan PR {$pr->pr_number}.",
                null,
                $this->snapshot($pr->fresh('currentVersion')),
                $actor,
            );
        });
    }

    /**
     * Move an eligible Draft PR into the recycle bin.
     */
    public function deleteDraft(PurchaseRequest $purchaseRequest, User $actor): void
    {
        DB::transaction(function () use ($purchaseRequest, $actor): void {
            $pr = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);

            if (! $pr->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya PR berstatus Draft yang dapat dihapus.',
                ]);
            }

            if ($pr->versions()->whereNotNull('finalized_at')->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'PR ini memiliki versi Final sehingga tidak dapat dihapus. Arsipkan atau buat revisi.',
                ]);
            }

            $this->recycleBin->register('purchase_request', $pr, $actor);
        });
    }

    /**
     * Generate a PDF for the current Final version without creating a revision.
     */
    public function generateDocument(PurchaseRequest $purchaseRequest, User $actor): GeneratedDocument
    {
        $version = $purchaseRequest->currentVersion;

        if ($version === null || ! $version->isFinal()) {
            throw ValidationException::withMessages([
                'document' => 'PDF hanya dapat dibuat untuk versi PR yang sudah final.',
            ]);
        }

        return $this->pdf->generate($purchaseRequest, $version, $actor);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistVersion(PurchaseRequest $purchaseRequest, array $data, User $actor, int $versionNumber): PurchaseRequestVersion
    {
        $items = $this->buildItems($data['items']);

        $version = $purchaseRequest->versions()->create([
            'version_number' => $versionNumber,
            'recipient_department_id' => $data['recipient_department_id'],
            'subject' => $data['subject'],
            'pr_date' => $data['pr_date'],
            'category' => $data['category'] ?? null,
            'grand_total' => Money::sum(array_column($items, 'estimated_total')),
            'created_by' => $actor->id,
        ]);

        $version->items()->createMany($items);

        return $version;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildItems(array $items): array
    {
        $result = [];

        foreach (array_values($items) as $index => $item) {
            $quantity = Money::scale($item['quantity'], 3);
            $unitPrice = Money::scale($item['unit_price'], 2);

            $result[] = [
                'budget_name' => trim((string) $item['budget_name']),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'estimated_total' => Money::lineTotal($quantity, $unitPrice),
                'sort_order' => $index + 1,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(PurchaseRequest $purchaseRequest): array
    {
        $version = $purchaseRequest->currentVersion;

        return [
            'pr_number' => $purchaseRequest->pr_number,
            'status' => $purchaseRequest->status,
            'version_number' => $version?->version_number,
            'subject' => $version?->subject,
            'grand_total' => $version?->grand_total,
        ];
    }
}
