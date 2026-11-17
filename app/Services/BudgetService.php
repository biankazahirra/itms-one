<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\GeneratedDocument;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestVersion;
use App\Models\Unit;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BudgetService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly RecycleBinService $recycleBin,
        private readonly DocumentNumberService $numbers,
        private readonly BudgetPdfService $pdf,
    ) {}

    /**
     * Create a Draft RAB, optionally imported from a saved PR.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?PurchaseRequest $sourcePr = null): Budget
    {
        return DB::transaction(function () use ($data, $actor, $sourcePr): Budget {
            $sourceVersion = null;

            if ($sourcePr !== null) {
                $sourcePr = PurchaseRequest::query()->withTrashed()->lockForUpdate()->findOrFail($sourcePr->id);
                $sourcePr->loadMissing('currentVersion.items');
                $sourceVersion = $sourcePr->currentVersion;

                if ($sourceVersion === null) {
                    throw ValidationException::withMessages([
                        'purchase_request_id' => 'PR sumber tidak memiliki versi.',
                    ]);
                }

                $items = $this->importPrItems($sourceVersion);
                $data['work_name'] = $sourceVersion->subject;
                $data['rab_date'] = $sourceVersion->pr_date->format('Y-m-d');
            } else {
                $items = $this->buildItems($data['items']);
            }

            $budget = Budget::create([
                'rab_number' => $this->numbers->next('RAB'),
                'purchase_request_id' => $sourcePr?->id,
                'status' => Budget::STATUS_DRAFT,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $version = $this->persistVersion($budget, $data, $actor, 1, $sourceVersion?->id, $items);

            $budget->forceFill(['current_version_id' => $version->id])->save();

            $this->activity->record(
                'budget',
                $budget->id,
                'created',
                "Membuat RAB {$budget->rab_number}.".($sourcePr !== null ? " Impor dari {$sourcePr->pr_number}." : ''),
                null,
                $this->snapshot($budget->fresh('currentVersion')),
                $actor,
            );

            return $budget;
        });
    }

    /**
     * Replace the header and items of the current Draft version.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(Budget $budget, array $data, User $actor): void
    {
        DB::transaction(function () use ($budget, $data, $actor): void {
            $budget = Budget::query()->lockForUpdate()->findOrFail($budget->id);

            if (! $budget->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya RAB berstatus Draft yang dapat diubah.',
                ]);
            }

            $version = $budget->currentVersion;

            if ($version === null || $version->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => 'Versi RAB ini sudah final dan tidak dapat diubah.',
                ]);
            }

            $before = $this->snapshot($budget);

            $items = $this->buildItems($data['items']);

            $version->forceFill([
                'work_name' => $data['work_name'],
                'rab_date' => $data['rab_date'],
                'location' => Budget::LOCATION,
                'grand_total' => Money::sum(array_column($items, 'line_total')),
            ])->save();

            $version->items()->delete();
            $version->items()->createMany($items);

            $budget->forceFill(['updated_by' => $actor->id])->save();

            $this->activity->record(
                'budget',
                $budget->id,
                'updated',
                "Memperbarui draft RAB {$budget->rab_number}.",
                $before,
                $this->snapshot($budget->fresh('currentVersion')),
                $actor,
            );
        });
    }

    /**
     * Finalize the current Draft version. Finalized versions are immutable.
     * Linked RABs are blocked unless their total equals the linked PR total.
     */
    public function finalize(Budget $budget, User $actor, bool $generateDocument = false): ?GeneratedDocument
    {
        return DB::transaction(function () use ($budget, $actor, $generateDocument): ?GeneratedDocument {
            $budget = Budget::query()->lockForUpdate()->findOrFail($budget->id);

            if (! $budget->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya RAB berstatus Draft yang dapat difinalisasi.',
                ]);
            }

            $version = $budget->currentVersion;

            if ($version === null || $version->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => 'Versi RAB ini sudah final.',
                ]);
            }

            $items = $version->items()->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'RAB harus memiliki minimal satu baris anggaran sebelum difinalisasi.',
                ]);
            }

            $grandTotal = Money::sum($items->pluck('line_total')->all());

            if ($budget->purchase_request_id !== null) {
                $prTotal = $this->linkedPrTotal($budget->purchase_request_id);

                if (bccomp($grandTotal, $prTotal, 2) !== 0) {
                    throw ValidationException::withMessages([
                        'total' => "Total RAB ({$grandTotal}) belum sama dengan total PR ({$prTotal}). Samakan dulu sebelum finalisasi.",
                    ]);
                }
            }

            $version->forceFill([
                'grand_total' => $grandTotal,
                'finalized_by' => $actor->id,
                'finalized_at' => now(),
            ])->save();

            $budget->forceFill([
                'status' => Budget::STATUS_FINAL,
                'finalized_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            $this->activity->record(
                'budget',
                $budget->id,
                'finalized',
                "Memfinalisasi RAB {$budget->rab_number} versi {$version->label()}.",
                null,
                $this->snapshot($budget->fresh('currentVersion')),
                $actor,
            );

            return $generateDocument ? $this->pdf->generate($budget, $version, $actor) : null;
        });
    }

    /**
     * Create the next Draft version from the current Final version.
     */
    public function createRevision(Budget $budget, User $actor): BudgetVersion
    {
        return DB::transaction(function () use ($budget, $actor): BudgetVersion {
            $budget = Budget::query()->lockForUpdate()->findOrFail($budget->id);

            if ($budget->status !== Budget::STATUS_FINAL) {
                throw ValidationException::withMessages([
                    'status' => 'Revisi hanya dapat dibuat dari RAB berstatus Final.',
                ]);
            }

            $source = $budget->currentVersion;

            if ($source === null || ! $source->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => 'Versi final RAB tidak ditemukan.',
                ]);
            }

            $nextNumber = ((int) $budget->versions()->max('version_number')) + 1;

            $version = $budget->versions()->create([
                'version_number' => $nextNumber,
                'source_pr_version_id' => $source->source_pr_version_id,
                'work_name' => $source->work_name,
                'rab_date' => $source->rab_date,
                'location' => $source->location,
                'grand_total' => $source->grand_total,
                'created_by' => $actor->id,
            ]);

            foreach ($source->items()->get() as $item) {
                $version->items()->create([
                    'budget_name' => $item->budget_name,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_id' => $item->unit_id,
                    'unit_name_snapshot' => $item->unit_name_snapshot,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                    'sort_order' => $item->sort_order,
                ]);
            }

            $budget->forceFill([
                'status' => Budget::STATUS_DRAFT,
                'current_version_id' => $version->id,
                'archived_at' => null,
                'finalized_at' => null,
                'updated_by' => $actor->id,
            ])->save();

            $this->activity->record(
                'budget',
                $budget->id,
                'revision_created',
                "Membuat revisi {$version->label()} untuk RAB {$budget->rab_number}.",
                null,
                $this->snapshot($budget->fresh('currentVersion')),
                $actor,
            );

            return $version;
        });
    }

    public function archive(Budget $budget, User $actor): void
    {
        DB::transaction(function () use ($budget, $actor): void {
            $budget = Budget::query()->lockForUpdate()->findOrFail($budget->id);

            if ($budget->status !== Budget::STATUS_FINAL) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya RAB berstatus Final yang dapat diarsipkan.',
                ]);
            }

            $budget->forceFill([
                'status' => Budget::STATUS_ARCHIVED,
                'archived_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            $this->activity->record(
                'budget',
                $budget->id,
                'archived',
                "Mengarsipkan RAB {$budget->rab_number}.",
                null,
                $this->snapshot($budget->fresh('currentVersion')),
                $actor,
            );
        });
    }

    /**
     * Move an eligible Draft RAB into the recycle bin.
     */
    public function deleteDraft(Budget $budget, User $actor): void
    {
        DB::transaction(function () use ($budget, $actor): void {
            $budget = Budget::query()->lockForUpdate()->findOrFail($budget->id);

            if (! $budget->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya RAB berstatus Draft yang dapat dihapus.',
                ]);
            }

            if ($budget->versions()->whereNotNull('finalized_at')->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'RAB ini memiliki versi Final sehingga tidak dapat dihapus. Arsipkan atau buat revisi.',
                ]);
            }

            $this->recycleBin->register('budget', $budget, $actor);
        });
    }

    /**
     * Generate a PDF for the current Final version without creating a revision.
     */
    public function generateDocument(Budget $budget, User $actor): GeneratedDocument
    {
        $version = $budget->currentVersion;

        if ($version === null || ! $version->isFinal()) {
            throw ValidationException::withMessages([
                'document' => 'PDF hanya dapat dibuat untuk versi RAB yang sudah final.',
            ]);
        }

        return $this->pdf->generate($budget, $version, $actor);
    }

    /**
     * Grand total of the linked PR's current version, or 0 when unavailable.
     */
    public function linkedPrTotal(int $purchaseRequestId): string
    {
        $pr = PurchaseRequest::withTrashed()->with('currentVersion')->find($purchaseRequestId);

        return $pr?->currentVersion !== null ? (string) $pr->currentVersion->grand_total : '0.00';
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $items
     */
    private function persistVersion(Budget $budget, array $data, User $actor, int $versionNumber, ?int $sourcePrVersionId, array $items): BudgetVersion
    {
        $version = $budget->versions()->create([
            'version_number' => $versionNumber,
            'source_pr_version_id' => $sourcePrVersionId,
            'work_name' => $data['work_name'],
            'rab_date' => $data['rab_date'],
            'location' => Budget::LOCATION,
            'grand_total' => Money::sum(array_column($items, 'line_total')),
            'created_by' => $actor->id,
        ]);

        $version->items()->createMany($items);

        return $version;
    }

    /**
     * Copy the stored PR unit price directly; never reverse-calculate it
     * from the line total.
     *
     * @return array<int, array<string, mixed>>
     */
    private function importPrItems(PurchaseRequestVersion $sourceVersion): array
    {
        $result = [];

        foreach ($sourceVersion->items as $index => $item) {
            $result[] = [
                'budget_name' => $item->budget_name,
                'description' => null,
                'quantity' => $item->quantity,
                'unit_id' => null,
                'unit_name_snapshot' => null,
                'unit_price' => $item->unit_price,
                'line_total' => Money::lineTotal((string) $item->quantity, (string) $item->unit_price),
                'sort_order' => $index + 1,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildItems(array $items): array
    {
        $unitIds = collect($items)->pluck('unit_id')->filter()->unique()->all();
        $units = Unit::query()->whereIn('id', $unitIds)->pluck('unit_name', 'id');

        $result = [];

        foreach (array_values($items) as $index => $item) {
            $quantity = Money::scale($item['quantity'], 3);
            $unitPrice = Money::scale($item['unit_price'], 2);
            $unitId = $item['unit_id'] ?? null;

            $result[] = [
                'budget_name' => trim((string) $item['budget_name']),
                'description' => isset($item['description']) && trim((string) $item['description']) !== ''
                    ? trim((string) $item['description'])
                    : null,
                'quantity' => $quantity,
                'unit_id' => $unitId,
                'unit_name_snapshot' => $unitId !== null ? $units->get((int) $unitId) : null,
                'unit_price' => $unitPrice,
                'line_total' => Money::lineTotal($quantity, $unitPrice),
                'sort_order' => $index + 1,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Budget $budget): array
    {
        $version = $budget->currentVersion;

        return [
            'rab_number' => $budget->rab_number,
            'status' => $budget->status,
            'version_number' => $version?->version_number,
            'work_name' => $version?->work_name,
            'grand_total' => $version?->grand_total,
        ];
    }
}
