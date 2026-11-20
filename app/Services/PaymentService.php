<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\PaymentEvidence;
use App\Models\PaymentRecord;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly PaymentStatusCalculator $calculator,
        private readonly PrivateFileService $files,
        private readonly RecycleBinService $recycleBin,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(Contract $contract, array $data, User $actor): PaymentRecord
    {
        return DB::transaction(function () use ($contract, $data, $actor): PaymentRecord {
            $amount = Money::scale($data['payment_amount'], 2);

            if (bccomp($amount, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'payment_amount' => 'Nominal pembayaran harus lebih besar dari nol.',
                ]);
            }

            $record = $contract->payments()->create([
                'contract_termin_id' => $this->terminId($contract, $data['contract_termin_id'] ?? null),
                'status' => PaymentRecord::STATUS_DRAFT,
                'payment_date' => $data['payment_date'],
                'payment_amount' => $amount,
                'payment_reference' => trim((string) $data['payment_reference']),
                'created_by' => $actor->id,
            ]);

            $this->activity->record(
                'contract',
                $contract->id,
                'payment_recorded',
                "Mencatat pembayaran {$record->payment_reference} pada kontrak {$contract->po_number}.",
                null,
                ['payment_record_id' => $record->id, 'payment_amount' => $amount],
                $actor,
            );

            return $record;
        });
    }

    /**
     * Confirmation is the only point where a record affects paid totals, so
     * the overpayment check runs here against the current obligation.
     */
    public function confirm(PaymentRecord $record, User $actor): void
    {
        DB::transaction(function () use ($record, $actor): void {
            $record = PaymentRecord::query()->lockForUpdate()->findOrFail($record->id);

            if (! $record->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya catatan pembayaran Draft yang dapat dikonfirmasi.',
                ]);
            }

            $contract = $record->contract;
            $paidAfter = bcadd($this->calculator->confirmedTotal($contract), $record->payment_amount, 2);
            $obligation = $this->calculator->obligation($contract);

            if (bccomp($paidAfter, $obligation, 2) > 0) {
                throw ValidationException::withMessages([
                    'payment_amount' => 'Konfirmasi ini membuat total pembayaran melebihi kewajiban kontrak.',
                ]);
            }

            if (bccomp($paidAfter, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    'payment_amount' => 'Koreksi melebihi total pembayaran Confirmed.',
                ]);
            }

            $record->forceFill(['status' => PaymentRecord::STATUS_CONFIRMED])->save();

            $this->activity->record(
                'contract',
                $contract->id,
                'payment_confirmed',
                "Mengonfirmasi pembayaran {$record->payment_reference} pada kontrak {$contract->po_number}.",
                ['status' => PaymentRecord::STATUS_DRAFT],
                ['status' => PaymentRecord::STATUS_CONFIRMED, 'payment_record_id' => $record->id],
                $actor,
            );
        });
    }

    /**
     * Confirmed records are never edited. A correction appends a new Draft
     * entry whose signed amount is the delta against the original.
     *
     * @param  array<string, mixed>  $data
     */
    public function correct(PaymentRecord $original, array $data, User $actor): PaymentRecord
    {
        return DB::transaction(function () use ($original, $data, $actor): PaymentRecord {
            $original = PaymentRecord::query()->lockForUpdate()->findOrFail($original->id);

            if (! $original->isConfirmed()) {
                throw ValidationException::withMessages([
                    'status' => 'Koreksi hanya dapat dibuat untuk catatan pembayaran Confirmed.',
                ]);
            }

            $amount = Money::scale($data['payment_amount'], 2);

            if (bccomp($amount, '0', 2) === 0) {
                throw ValidationException::withMessages([
                    'payment_amount' => 'Nominal koreksi tidak boleh nol.',
                ]);
            }

            $record = $original->contract->payments()->create([
                'contract_termin_id' => $original->contract_termin_id,
                'corrects_payment_record_id' => $original->id,
                'status' => PaymentRecord::STATUS_DRAFT,
                'payment_date' => $data['payment_date'],
                'payment_amount' => $amount,
                'payment_reference' => trim((string) $data['payment_reference']),
                'created_by' => $actor->id,
            ]);

            $this->activity->record(
                'contract',
                $original->contract_id,
                'payment_corrected',
                "Mencatat koreksi pembayaran untuk {$original->payment_reference}.",
                ['payment_record_id' => $original->id],
                ['payment_record_id' => $record->id, 'payment_amount' => $amount],
                $actor,
            );

            return $record;
        });
    }

    public function deleteDraft(PaymentRecord $record, User $actor): void
    {
        DB::transaction(function () use ($record, $actor): void {
            $record = PaymentRecord::query()->lockForUpdate()->findOrFail($record->id);

            if (! $record->canBeDeleted()) {
                throw ValidationException::withMessages([
                    'status' => 'Catatan pembayaran Confirmed tidak dapat dihapus. Gunakan koreksi.',
                ]);
            }

            if ($record->evidences()->withTrashed()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Hapus bukti pembayaran terlebih dahulu.',
                ]);
            }

            $description = "Menghapus draf pembayaran {$record->payment_reference}.";
            $contractId = $record->contract_id;
            $values = ['payment_record_id' => $record->id, 'payment_amount' => (string) $record->payment_amount];

            $record->delete();

            $this->activity->record(
                'contract',
                $contractId,
                'payment_deleted',
                $description,
                $values,
                null,
                $actor,
            );
        });
    }

    public function addEvidence(PaymentRecord $record, UploadedFile $file, User $actor): PaymentEvidence
    {
        return DB::transaction(function () use ($record, $file, $actor): PaymentEvidence {
            $path = $this->files->put(
                "payment-evidences/{$record->id}",
                $file,
                Str::uuid()->toString().'.'.$file->getClientOriginalExtension(),
            );

            $evidence = $record->evidences()->create([
                'original_filename' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => (int) $file->getSize(),
                'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                'uploaded_by' => $actor->id,
                'uploaded_at' => now(),
            ]);

            $this->activity->record(
                'contract',
                $record->contract_id,
                'evidence_uploaded',
                "Menambahkan bukti pembayaran {$evidence->original_filename}.",
                null,
                ['payment_record_id' => $record->id, 'payment_evidence_id' => $evidence->id],
                $actor,
            );

            return $evidence;
        });
    }

    public function deleteEvidence(PaymentEvidence $evidence, User $actor): void
    {
        DB::transaction(function () use ($evidence, $actor): void {
            $this->recycleBin->register('payment_evidence', $evidence, $actor);
        });
    }

    private function terminId(Contract $contract, mixed $terminId): ?int
    {
        if ($terminId === null || $terminId === '') {
            return null;
        }

        if ($contract->payment_method !== Contract::PAYMENT_TERMIN) {
            return null;
        }

        $belongs = $contract->termins()->whereKey((int) $terminId)->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'contract_termin_id' => 'Termin tidak termasuk kontrak ini.',
            ]);
        }

        return (int) $terminId;
    }
}
