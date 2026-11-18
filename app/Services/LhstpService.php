<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Lhstp;
use App\Models\LhstpVersion;
use App\Models\LhstpVersionTermin;
use App\Models\User;
use App\Support\Money;
use App\Support\Terbilang;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LhstpService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly RecycleBinService $recycleBin,
        private readonly LhstpPaymentCalculator $calculator,
        private readonly LhstpDocumentService $documents,
    ) {}

    /**
     * Inclusive calendar days between two realization dates.
     */
    public function realizationDays(string $start, string $end): int
    {
        return (int) Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
    }

    /**
     * Values pre-filled from the selected contract so the user can adjust them.
     *
     * @return array<string, string>
     */
    public function prefill(Contract $contract): array
    {
        $method = $contract->payment_method;
        $amount = match ($method) {
            Contract::PAYMENT_MONTHLY => (string) $contract->monthly_payment_amount,
            Contract::PAYMENT_FULL => (string) $contract->full_financing_amount,
            default => '',
        };

        $rate = Lhstp::DEFAULT_PPN_RATE;
        $period = today()->locale('id')->translatedFormat('F Y');

        return [
            'payment_amount' => $amount,
            'ppn_rate' => $rate,
            'cost_center' => Lhstp::DEFAULT_COST_CENTER,
            'work_period' => $period,
            'opname_date' => today()->format('Y-m-d'),
            'signature_date' => today()->format('Y-m-d'),
            'work_conclusion' => 'Hasil pekerjaan dapat diterima sebagai Penyelesaian pekerjaan periode '.$period.'.',
            'payment_terms' => $this->paymentTermsText($contract, $amount),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Lhstp
    {
        return DB::transaction(function () use ($data, $actor): Lhstp {
            $contract = $this->selectableContract((int) $data['contract_id']);
            $this->assertNumberAvailable($data['lhstp_number']);
            $this->assertTerminAvailable($contract, $data['contract_termin_id'] ?? null);
            $this->assertDateOrder($data);

            $calculated = $this->calculatedAttributes($contract, $data);

            if ($this->exceedsContractValue($contract, $calculated['total_payment'], null)
                && ! filter_var($data['confirm_exceed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                throw ValidationException::withMessages([
                    'confirm_exceed' => 'Total pembayaran LHSTP melebihi nilai kontrak. Centang konfirmasi untuk melanjutkan.',
                ]);
            }

            $lhstp = Lhstp::create([
                'lhstp_number' => trim((string) $data['lhstp_number']),
                'contract_id' => $contract->id,
                'status' => Lhstp::STATUS_DRAFT,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $version = $lhstp->versions()->create($calculated['version'] + [
                'version_number' => 1,
                'created_by' => $actor->id,
            ]);

            $this->storeTermin($version, $contract, $data['contract_termin_id'] ?? null);

            $lhstp->forceFill(['current_version_id' => $version->id])->save();

            $this->activity->record(
                'lhstp',
                $lhstp->id,
                'created',
                "Membuat LHSTP {$lhstp->lhstp_number} — {$version->work_name_snapshot}.",
                null,
                ['status' => Lhstp::STATUS_DRAFT, 'version_id' => $version->id],
                $actor,
            );

            return $lhstp;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(Lhstp $lhstp, array $data, User $actor): void
    {
        DB::transaction(function () use ($lhstp, $data, $actor): void {
            $lhstp = Lhstp::query()->lockForUpdate()->findOrFail($lhstp->id);

            if (! $lhstp->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya LHSTP berstatus Draft yang dapat diubah.',
                ]);
            }

            $version = $lhstp->currentVersion;

            if ($version === null || $version->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => 'Versi LHSTP ini sudah final dan tidak dapat diubah.',
                ]);
            }

            $contract = $this->selectableContract((int) $data['contract_id']);
            $this->assertNumberAvailable($data['lhstp_number'], $lhstp->id);
            $this->assertTerminAvailable($contract, $data['contract_termin_id'] ?? null, $lhstp->id);
            $this->assertDateOrder($data);

            $calculated = $this->calculatedAttributes($contract, $data);

            if ($this->exceedsContractValue($contract, $calculated['total_payment'], $lhstp->id)
                && ! filter_var($data['confirm_exceed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                throw ValidationException::withMessages([
                    'confirm_exceed' => 'Total pembayaran LHSTP melebihi nilai kontrak. Centang konfirmasi untuk melanjutkan.',
                ]);
            }

            $version->forceFill($calculated['version'])->save();

            $version->termin()->delete();
            $this->storeTermin($version, $contract, $data['contract_termin_id'] ?? null);

            $lhstp->forceFill([
                'lhstp_number' => trim((string) $data['lhstp_number']),
                'contract_id' => $contract->id,
                'updated_by' => $actor->id,
            ])->save();

            $this->activity->record(
                'lhstp',
                $lhstp->id,
                'updated',
                "Memperbarui LHSTP {$lhstp->lhstp_number}.",
                null,
                ['status' => $lhstp->status, 'version_id' => $version->id],
                $actor,
            );
        });
    }

    public function finalize(Lhstp $lhstp, User $actor, bool $generateDocument = false): void
    {
        DB::transaction(function () use ($lhstp, $actor, $generateDocument): void {
            $lhstp = Lhstp::query()->lockForUpdate()->findOrFail($lhstp->id);

            if (! $lhstp->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya LHSTP berstatus Draft yang dapat difinalisasi.',
                ]);
            }

            $version = $lhstp->currentVersion;

            if ($version === null || $version->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => 'Versi LHSTP ini sudah final.',
                ]);
            }

            $this->assertTerminAvailable($version->lhstp->contract, $version->termin?->contract_termin_id, $lhstp->id);

            $version->forceFill([
                'finalized_by' => $actor->id,
                'finalized_at' => now(),
            ])->save();

            $lhstp->forceFill([
                'status' => Lhstp::STATUS_FINAL,
                'finalized_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            $this->activity->record(
                'lhstp',
                $lhstp->id,
                'finalized',
                "Finalisasi LHSTP {$lhstp->lhstp_number} {$version->label()}.",
                ['status' => Lhstp::STATUS_DRAFT],
                ['status' => Lhstp::STATUS_FINAL, 'version_id' => $version->id],
                $actor,
            );

            if ($generateDocument) {
                $this->documents->generate($lhstp, $version, $actor, 'PDF');
            }
        });
    }

    public function createRevision(Lhstp $lhstp, User $actor): LhstpVersion
    {
        return DB::transaction(function () use ($lhstp, $actor): LhstpVersion {
            $lhstp = Lhstp::query()->lockForUpdate()->findOrFail($lhstp->id);

            if (! $lhstp->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => 'Revisi hanya dapat dibuat dari LHSTP berstatus Final.',
                ]);
            }

            $source = $lhstp->currentVersion;

            if ($source === null) {
                throw ValidationException::withMessages([
                    'status' => 'Versi final LHSTP tidak ditemukan.',
                ]);
            }

            $source->loadMissing('termin');

            $next = ((int) $lhstp->versions()->max('version_number')) + 1;

            $version = $lhstp->versions()->create([
                ...$this->copyableAttributes($source),
                'version_number' => $next,
                'created_by' => $actor->id,
                'finalized_by' => null,
                'finalized_at' => null,
            ]);

            if ($source->termin !== null) {
                $version->termin()->create([
                    'contract_termin_id' => $source->termin->contract_termin_id,
                    'termin_name_snapshot' => $source->termin->termin_name_snapshot,
                    'percentage_snapshot' => $source->termin->percentage_snapshot,
                    'nominal_snapshot' => $source->termin->nominal_snapshot,
                    'created_at' => now(),
                ]);
            }

            $lhstp->forceFill([
                'status' => Lhstp::STATUS_DRAFT,
                'current_version_id' => $version->id,
                'finalized_at' => null,
                'updated_by' => $actor->id,
            ])->save();

            $this->activity->record(
                'lhstp',
                $lhstp->id,
                'revised',
                "Membuat revisi LHSTP {$lhstp->lhstp_number} {$version->label()}.",
                ['status' => Lhstp::STATUS_FINAL],
                ['status' => Lhstp::STATUS_DRAFT, 'version_id' => $version->id],
                $actor,
            );

            return $version;
        });
    }

    public function archive(Lhstp $lhstp, User $actor): void
    {
        DB::transaction(function () use ($lhstp, $actor): void {
            $lhstp = Lhstp::query()->lockForUpdate()->findOrFail($lhstp->id);

            if (! $lhstp->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya LHSTP berstatus Final yang dapat diarsipkan.',
                ]);
            }

            $lhstp->forceFill([
                'status' => Lhstp::STATUS_ARCHIVED,
                'archived_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            $this->activity->record(
                'lhstp',
                $lhstp->id,
                'archived',
                "Mengarsipkan LHSTP {$lhstp->lhstp_number}.",
                ['status' => Lhstp::STATUS_FINAL],
                ['status' => Lhstp::STATUS_ARCHIVED],
                $actor,
            );
        });
    }

    public function deleteDraft(Lhstp $lhstp, User $actor): void
    {
        DB::transaction(function () use ($lhstp, $actor): void {
            $lhstp = Lhstp::query()->lockForUpdate()->findOrFail($lhstp->id);

            if (! $lhstp->canBeDeleted()) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya LHSTP Draft tanpa versi Final yang dapat dihapus.',
                ]);
            }

            $this->recycleBin->register('lhstp', $lhstp, $actor);
        });
    }

    /**
     * Warnings that do not block saving.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public function warnings(Contract $contract, array $data, ?int $ignoreLhstpId = null): array
    {
        $warnings = [];

        $duplicate = LhstpVersion::query()
            ->where('reference_document_number', trim((string) $data['reference_document_number']))
            ->whereHas('lhstp', fn ($query) => $query->whereKeyNot($ignoreLhstpId ?? 0))
            ->exists();

        if ($duplicate) {
            $warnings[] = "Nomor dokumen referensi {$data['reference_document_number']} sudah dipakai LHSTP lain.";
        }

        $overlap = LhstpVersion::query()
            ->where('lhstp_id', '!=', $ignoreLhstpId ?? 0)
            ->whereHas('lhstp', fn ($query) => $query->where('contract_id', $contract->id))
            ->whereDate('realization_start', '<=', $data['realization_end'])
            ->whereDate('realization_end', '>=', $data['realization_start'])
            ->with('lhstp')
            ->first();

        if ($overlap !== null) {
            $warnings[] = "LHSTP {$overlap->lhstp->lhstp_number} pada kontrak ini memakai periode pekerjaan yang tumpang tindih.";
        }

        if (Carbon::parse($data['realization_start'])->lt($contract->start_date)
            || Carbon::parse($data['realization_end'])->gt($contract->end_date)) {
            $warnings[] = 'Tanggal realisasi di luar periode kontrak.';
        }

        return $warnings;
    }

    public function exceedsContractValue(Contract $contract, string $totalPayment, ?int $ignoreLhstpId): bool
    {
        $committed = bcadd($this->committedTotal($contract, $ignoreLhstpId), Money::scale($totalPayment, 2), 2);

        return bccomp($committed, Money::scale($contract->contract_value, 2), 2) > 0;
    }

    /**
     * Sum of Draft and Final LHSTP total payments for one contract, so a new
     * LHSTP must confirm when the contract value is exceeded.
     */
    public function committedTotal(Contract $contract, ?int $ignoreLhstpId = null): string
    {
        $versionIds = Lhstp::query()
            ->where('contract_id', $contract->id)
            ->whereIn('status', [Lhstp::STATUS_DRAFT, Lhstp::STATUS_FINAL])
            ->when($ignoreLhstpId !== null, fn ($query) => $query->whereKeyNot($ignoreLhstpId))
            ->whereNotNull('current_version_id')
            ->pluck('current_version_id');

        return Money::sum(
            LhstpVersion::query()->whereIn('id', $versionIds)->pluck('total_payment')->all()
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertDateOrder(array $data): void
    {
        if (Carbon::parse($data['realization_end'])->lt(Carbon::parse($data['realization_start']))) {
            throw ValidationException::withMessages([
                'realization_end' => 'Tanggal selesai realisasi tidak boleh lebih awal dari tanggal mulai.',
            ]);
        }
    }

    private function selectableContract(int $contractId): Contract
    {
        $contract = Contract::query()->find($contractId);

        if ($contract === null || ! $contract->isSelectableForLhstp()) {
            throw ValidationException::withMessages([
                'contract_id' => 'Kontrak tidak tersedia untuk LHSTP baru.',
            ]);
        }

        return $contract;
    }

    private function assertNumberAvailable(string $number, ?int $ignoreId = null): void
    {
        $exists = Lhstp::withTrashed()
            ->where('lhstp_number', trim($number))
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'lhstp_number' => 'Nomor LHSTP sudah digunakan oleh LHSTP lain.',
            ]);
        }
    }

    private function assertTerminAvailable(Contract $contract, mixed $terminId, ?int $ignoreLhstpId = null): void
    {
        if ($contract->payment_method !== Contract::PAYMENT_TERMIN) {
            return;
        }

        if (blank($terminId)) {
            throw ValidationException::withMessages([
                'contract_termin_id' => 'Pilih satu termin yang ditagihkan.',
            ]);
        }

        $termin = $contract->termins()->whereKey($terminId)->first();

        if ($termin === null) {
            throw ValidationException::withMessages([
                'contract_termin_id' => 'Termin tidak termasuk kontrak yang dipilih.',
            ]);
        }

        $used = LhstpVersionTermin::query()
            ->where('contract_termin_id', $termin->id)
            ->whereHas('version', fn ($query) => $query->whereNotNull('finalized_at'))
            ->whereHas('version.lhstp', fn ($query) => $query->whereKeyNot($ignoreLhstpId ?? 0))
            ->exists();

        if ($used) {
            throw ValidationException::withMessages([
                'contract_termin_id' => 'Termin ini sudah dipakai LHSTP Final lain.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{version: array<string, mixed>, total_payment: string}
     */
    private function calculatedAttributes(Contract $contract, array $data): array
    {
        $calculated = $this->calculator->calculate(
            (string) $data['payment_amount'],
            (string) $data['ppn_rate'],
            $contract->ppn_status,
        );

        return [
            'total_payment' => $calculated['total_payment'],
            'version' => [
                'po_number_snapshot' => $contract->po_number,
                'contract_date_snapshot' => $contract->contract_date,
                'vendor_name_snapshot' => $contract->vendor?->company_name ?? '—',
                'vendor_address_snapshot' => $contract->vendor?->company_address,
                'work_name_snapshot' => $contract->work_name,
                'contract_value_snapshot' => Money::scale($contract->contract_value, 2),
                'contract_start_snapshot' => $contract->start_date,
                'contract_end_snapshot' => $contract->end_date,
                'representative_name_snapshot' => $contract->representative_name,
                'representative_position_snapshot' => $contract->representative_position,
                'payment_method_snapshot' => $contract->payment_method,
                'ppn_status_snapshot' => $contract->ppn_status,
                'lhstp_date' => $data['lhstp_date'],
                'work_period' => trim((string) $data['work_period']),
                'realization_start' => $data['realization_start'],
                'realization_end' => $data['realization_end'],
                'realization_days' => $this->realizationDays($data['realization_start'], $data['realization_end']),
                'reference_document_type' => $data['reference_document_type'],
                'reference_document_name' => $data['reference_document_type'] === Lhstp::REFERENCE_OTHER
                    ? trim((string) $data['reference_document_name'])
                    : null,
                'reference_document_number' => trim((string) $data['reference_document_number']),
                'reference_document_date' => $data['reference_document_date'],
                'work_conclusion' => trim((string) $data['work_conclusion']),
                'payment_terms' => trim((string) $data['payment_terms']),
                'payment_amount' => Money::scale($data['payment_amount'], 2),
                'ppn_rate' => Money::scale($data['ppn_rate'], 4),
                'dpp_lain' => $calculated['dpp_lain'],
                'ppn_amount' => $calculated['ppn_amount'],
                'total_payment' => $calculated['total_payment'],
                'terbilang' => Terbilang::rupiah($calculated['total_payment']),
                'cost_center' => trim((string) $data['cost_center']),
                'opname_date' => $data['opname_date'] ?? $data['lhstp_date'],
                'signature_date' => $data['signature_date'] ?? $data['lhstp_date'],
            ],
        ];
    }

    private function storeTermin(LhstpVersion $version, Contract $contract, mixed $terminId): void
    {
        if ($contract->payment_method !== Contract::PAYMENT_TERMIN || blank($terminId)) {
            return;
        }

        $termin = $contract->termins()->whereKey($terminId)->firstOrFail();

        $version->termin()->create([
            'contract_termin_id' => $termin->id,
            'termin_name_snapshot' => $termin->termin_name,
            'percentage_snapshot' => $termin->percentage,
            'nominal_snapshot' => $termin->nominal_amount,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function copyableAttributes(LhstpVersion $version): array
    {
        return collect($version->getAttributes())
            ->except(['id', 'lhstp_id', 'version_number', 'finalized_by', 'finalized_at', 'created_at', 'updated_at'])
            ->all();
    }

    private function paymentTermsText(Contract $contract, string $amount): string
    {
        $formatted = $this->groupedAmount($amount);

        return match ($contract->payment_method) {
            Contract::PAYMENT_TERMIN => 'Berdasarkan ketentuan Purchase Order, pembayaran dilakukan sebesar '.$formatted.' sesuai termin yang ditagihkan, akan dibayarkan Pihak Pertama sesuai hasil Opname Bersama di lapangan yang dinyatakan dengan LHSTP.',
            Contract::PAYMENT_FULL => 'Berdasarkan ketentuan Purchase Order, pembayaran dilakukan sekaligus sebesar '.$formatted.', akan dibayarkan Pihak Pertama sesuai hasil Opname Bersama di lapangan yang dinyatakan dengan LHSTP.',
            default => 'Berdasarkan ketentuan Purchase Order, pembayaran dilakukan per bulan sebesar '.$formatted.', akan dibayarkan Pihak Pertama sesuai hasil Opname Bersama di lapangan yang dinyatakan dengan LHSTP.',
        };
    }

    private function groupedAmount(string $amount): string
    {
        $whole = bcadd($amount === '' ? '0' : $amount, '0', 0);
        $chunks = array_map('strrev', array_reverse(str_split(strrev($whole), 3)));

        return 'Rp '.implode('.', $chunks);
    }
}
