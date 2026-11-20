<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractTermin;
use App\Models\Lhstp;
use App\Models\PaymentRecord;
use App\Support\Money;

class PaymentStatusCalculator
{
    public const UNPAID = 'Unpaid';

    public const PARTIALLY_PAID = 'Partially Paid';

    public const PAID = 'Paid';

    /**
     * Obligation is the accumulated total of Final LHSTP amounts for the
     * contract. A draft revision keeps its last finalized amount until the
     * revision is finalized; archived LHSTPs never contribute.
     */
    public function obligation(Contract $contract): string
    {
        $lhstps = Lhstp::query()
            ->where('contract_id', $contract->id)
            ->where('status', '!=', Lhstp::STATUS_ARCHIVED)
            ->with('versions')
            ->get();

        return Money::sum($lhstps->map(fn (Lhstp $lhstp): string => $this->finalizedTotal($lhstp))->all());
    }

    /**
     * Obligation as if one LHSTP no longer contributed its finalized amount.
     */
    public function obligationWithout(Contract $contract, Lhstp $lhstp): string
    {
        return bcsub($this->obligation($contract), $this->finalizedTotal($lhstp), 2);
    }

    public function confirmedTotal(Contract $contract): string
    {
        return Money::sum(
            PaymentRecord::query()
                ->where('contract_id', $contract->id)
                ->where('status', PaymentRecord::STATUS_CONFIRMED)
                ->pluck('payment_amount')
                ->all(),
        );
    }

    public function outstanding(Contract $contract): string
    {
        $outstanding = bcsub($this->obligation($contract), $this->confirmedTotal($contract), 2);

        return bccomp($outstanding, '0', 2) > 0 ? $outstanding : '0.00';
    }

    public function status(Contract $contract): string
    {
        $paid = $this->confirmedTotal($contract);

        if (bccomp($paid, '0', 2) <= 0) {
            return self::UNPAID;
        }

        return bccomp($paid, $this->obligation($contract), 2) >= 0
            ? self::PAID
            : self::PARTIALLY_PAID;
    }

    /**
     * Confirmed payments per termin, against each termin nominal.
     *
     * @return array<int, array{termin: ContractTermin, paid: string, status: string}>
     */
    public function terminProgress(Contract $contract): array
    {
        $paid = PaymentRecord::query()
            ->where('contract_id', $contract->id)
            ->where('status', PaymentRecord::STATUS_CONFIRMED)
            ->whereNotNull('contract_termin_id')
            ->get()
            ->groupBy('contract_termin_id');

        return $contract->termins->map(function ($termin) use ($paid): array {
            $amount = Money::sum($paid->get($termin->id, collect())->pluck('payment_amount')->all());
            $nominal = Money::scale($termin->nominal_amount, 2);

            return [
                'termin' => $termin,
                'paid' => $amount,
                'status' => bccomp($amount, '0', 2) <= 0
                    ? self::UNPAID
                    : (bccomp($amount, $nominal, 2) >= 0 ? self::PAID : self::PARTIALLY_PAID),
            ];
        })->all();
    }

    private function finalizedTotal(Lhstp $lhstp): string
    {
        $version = $lhstp->versions
            ->whereNotNull('finalized_at')
            ->sortByDesc('version_number')
            ->first();

        return $version === null ? '0.00' : Money::scale($version->total_payment, 2);
    }
}
