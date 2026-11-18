<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractActivityLog;
use App\Models\ContractAttachment;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContractService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly RecycleBinService $recycleBin,
        private readonly PrivateFileService $files,
        private readonly TerminAllocationService $termins,
        private readonly ContractStatusService $status,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $attachments
     */
    public function create(array $data, User $actor, array $attachments = []): Contract
    {
        return DB::transaction(function () use ($data, $actor, $attachments): Contract {
            $this->assertPoAvailable($data['po_number']);

            $payment = $this->paymentFields($data);
            $terminRows = $payment['termins'];

            $contract = $this->persist([
                'po_number' => trim((string) $data['po_number']),
                'contract_date' => $data['contract_date'],
                'vendor_id' => (int) $data['vendor_id'],
                'work_name' => trim((string) $data['work_name']),
                'representative_name' => trim((string) $data['representative_name']),
                'representative_position' => trim((string) $data['representative_position']),
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'contract_value' => Money::scale($data['contract_value'], 2),
                'ppn_status' => $data['ppn_status'],
                'payment_method' => $data['payment_method'],
                'monthly_payment_amount' => $payment['monthly'],
                'full_financing_amount' => $payment['full'],
                'status' => Carbon::parse($data['end_date'])->lt(today())
                    ? Contract::STATUS_INACTIVE
                    : Contract::STATUS_ACTIVE,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach ($terminRows as $row) {
                $contract->termins()->create($row);
            }

            $this->storeAttachments($contract, $attachments, $actor);

            $contract->load('termins');
            $after = $this->snapshot($contract);

            $this->logChange($contract, $actor, 'created', null, $after);
            $this->activity->record(
                'contract',
                $contract->id,
                'created',
                "Membuat kontrak {$contract->po_number} — {$contract->work_name}.",
                null,
                $after,
                $actor,
            );

            return $contract;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $attachments
     */
    public function update(Contract $contract, array $data, User $actor, array $attachments = []): void
    {
        DB::transaction(function () use ($contract, $data, $actor, $attachments): void {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            $contract->load('termins');

            $this->assertPoAvailable($data['po_number'], $contract->id);

            $before = $this->snapshot($contract);
            $payment = $this->paymentFields($data);

            $contract->forceFill([
                'po_number' => trim((string) $data['po_number']),
                'contract_date' => $data['contract_date'],
                'vendor_id' => (int) $data['vendor_id'],
                'work_name' => trim((string) $data['work_name']),
                'representative_name' => trim((string) $data['representative_name']),
                'representative_position' => trim((string) $data['representative_position']),
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'contract_value' => Money::scale($data['contract_value'], 2),
                'ppn_status' => $data['ppn_status'],
                'payment_method' => $data['payment_method'],
                'monthly_payment_amount' => $payment['monthly'],
                'full_financing_amount' => $payment['full'],
                'status' => $contract->isArchived()
                    ? Contract::STATUS_ARCHIVED
                    : (Carbon::parse($data['end_date'])->lt(today())
                        ? Contract::STATUS_INACTIVE
                        : Contract::STATUS_ACTIVE),
                'updated_by' => $actor->id,
            ])->save();

            // Termins consumed by a Final LHSTP are guarded once the LHSTP
            // module lands; until then the schedule is simply replaced.
            $contract->termins()->delete();

            foreach ($payment['termins'] as $row) {
                $contract->termins()->create($row);
            }

            $this->storeAttachments($contract, $attachments, $actor);

            $contract->load('termins');
            $after = $this->snapshot($contract);
            $changes = $this->changes($before, $after);

            if ($changes === []) {
                return;
            }

            $this->logChange($contract, $actor, 'updated', $changes['before'], $changes['after']);
            $this->activity->record(
                'contract',
                $contract->id,
                'updated',
                "Memperbarui kontrak {$contract->po_number}: {$changes['labels']}.",
                $changes['before'],
                $changes['after'],
                $actor,
            );
        });
    }

    public function archive(Contract $contract, User $actor): void
    {
        DB::transaction(function () use ($contract, $actor): void {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);

            if ($contract->isArchived()) {
                return;
            }

            $before = ['status' => $contract->status];
            $contract->forceFill([
                'status' => Contract::STATUS_ARCHIVED,
                'archived_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            $this->logChange($contract, $actor, 'archived', $before, ['status' => Contract::STATUS_ARCHIVED]);
            $this->activity->record(
                'contract',
                $contract->id,
                'archived',
                "Mengarsipkan kontrak {$contract->po_number}.",
                $before,
                ['status' => Contract::STATUS_ARCHIVED],
                $actor,
            );
        });
    }

    public function unarchive(Contract $contract, User $actor): void
    {
        DB::transaction(function () use ($contract, $actor): void {
            $contract = Contract::query()->lockForUpdate()->findOrFail($contract->id);

            if (! $contract->isArchived()) {
                return;
            }

            $status = $contract->end_date !== null && $contract->end_date->lt(today())
                ? Contract::STATUS_INACTIVE
                : Contract::STATUS_ACTIVE;

            $contract->forceFill([
                'status' => $status,
                'archived_at' => null,
                'updated_by' => $actor->id,
            ])->save();

            $this->logChange(
                $contract,
                $actor,
                'unarchived',
                ['status' => Contract::STATUS_ARCHIVED],
                ['status' => $status],
            );

            $this->activity->record(
                'contract',
                $contract->id,
                'unarchived',
                "Membatalkan arsip kontrak {$contract->po_number}.",
                ['status' => Contract::STATUS_ARCHIVED],
                ['status' => $status],
                $actor,
            );
        });
    }

    public function delete(Contract $contract, User $actor): void
    {
        DB::transaction(function () use ($contract, $actor): void {
            $this->recycleBin->register('contract', $contract, $actor);
        });
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function addAttachments(Contract $contract, array $attachments, User $actor): void
    {
        DB::transaction(function () use ($contract, $attachments, $actor): void {
            $this->storeAttachments($contract, $attachments, $actor);
        });
    }

    public function removeAttachment(Contract $contract, ContractAttachment $attachment, User $actor): void
    {
        if ($attachment->contract_id !== $contract->id) {
            throw ValidationException::withMessages([
                'attachment' => 'Lampiran tidak termasuk kontrak ini.',
            ]);
        }

        DB::transaction(function () use ($contract, $attachment, $actor): void {
            $this->files->delete($attachment->storage_path);

            $filename = $attachment->original_filename;
            $attachment->delete();

            $this->activity->record(
                'contract',
                $contract->id,
                'attachment_removed',
                "Menghapus lampiran {$filename} dari kontrak {$contract->po_number}.",
                ['original_filename' => $filename],
                null,
                $actor,
            );
        });
    }

    public function syncStatuses(): int
    {
        return $this->status->syncAll();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persist(array $attributes): Contract
    {
        try {
            return Contract::create($attributes);
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'po_number')) {
                throw ValidationException::withMessages([
                    'po_number' => 'Nomor PO sudah digunakan oleh kontrak lain.',
                ]);
            }

            throw $exception;
        }
    }

    private function assertPoAvailable(string $poNumber, ?int $ignoreId = null): void
    {
        $exists = Contract::withTrashed()
            ->where('po_number', trim($poNumber))
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'po_number' => 'Nomor PO sudah digunakan oleh kontrak lain.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{monthly: ?string, full: ?string, termins: array<int, array<string, mixed>>}
     */
    private function paymentFields(array $data): array
    {
        $method = $data['payment_method'];

        $monthly = $method === Contract::PAYMENT_MONTHLY
            ? Money::scale($data['monthly_payment_amount'], 2)
            : null;

        $full = $method === Contract::PAYMENT_FULL
            ? Money::scale($data['full_financing_amount'], 2)
            : null;

        $terminRows = $method === Contract::PAYMENT_TERMIN
            ? $this->termins->build(Money::scale($data['contract_value'], 2), $data['termins'] ?? [])
            : [];

        return ['monthly' => $monthly, 'full' => $full, 'termins' => $terminRows];
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    private function storeAttachments(Contract $contract, array $attachments, User $actor): void
    {
        foreach ($attachments as $file) {
            $directory = "contracts/{$contract->id}/attachments";
            $path = $this->files->put($directory, $file, Str::uuid()->toString().'.pdf');

            $attachment = $contract->attachments()->create([
                'original_filename' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/pdf',
                'file_size' => (int) $file->getSize(),
                'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                'uploaded_by' => $actor->id,
                'uploaded_at' => now(),
            ]);

            $this->activity->record(
                'contract',
                $contract->id,
                'attachment_added',
                "Menambahkan lampiran {$attachment->original_filename} pada kontrak {$contract->po_number}.",
                null,
                ['original_filename' => $attachment->original_filename],
                $actor,
            );
        }
    }

    private function logChange(
        Contract $contract,
        User $actor,
        string $action,
        ?array $before,
        ?array $after,
    ): void {
        ContractActivityLog::create([
            'contract_id' => $contract->id,
            'user_id' => $actor->id,
            'action' => $action,
            'before_values' => $before,
            'after_values' => $after,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Contract $contract): array
    {
        return [
            'po_number' => $contract->po_number,
            'contract_date' => $contract->contract_date?->format('Y-m-d'),
            'vendor_id' => (int) $contract->vendor_id,
            'work_name' => $contract->work_name,
            'representative_name' => $contract->representative_name,
            'representative_position' => $contract->representative_position,
            'start_date' => $contract->start_date?->format('Y-m-d'),
            'end_date' => $contract->end_date?->format('Y-m-d'),
            'contract_value' => (string) $contract->contract_value,
            'ppn_status' => $contract->ppn_status,
            'payment_method' => $contract->payment_method,
            'monthly_payment_amount' => $contract->monthly_payment_amount !== null ? (string) $contract->monthly_payment_amount : null,
            'full_financing_amount' => $contract->full_financing_amount !== null ? (string) $contract->full_financing_amount : null,
            'status' => $contract->status,
            'termins' => $contract->termins->map(fn ($termin) => [
                'termin_name' => $termin->termin_name,
                'percentage' => (string) $termin->percentage,
                'nominal_amount' => (string) $termin->nominal_amount,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{before: array<string, mixed>, after: array<string, mixed>, labels: string}|array{}
     */
    private function changes(array $before, array $after): array
    {
        $changedBefore = [];
        $changedAfter = [];
        $labels = [];

        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $changedBefore[$key] = $before[$key] ?? null;
                $changedAfter[$key] = $value;
                $labels[] = ContractActivityLog::FIELD_LABELS[$key] ?? $key;
            }
        }

        if ($changedAfter === []) {
            return [];
        }

        return [
            'before' => $changedBefore,
            'after' => $changedAfter,
            'labels' => implode(', ', $labels),
        ];
    }
}
