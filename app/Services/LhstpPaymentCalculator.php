<?php

namespace App\Services;

use App\Models\Contract;
use App\Support\Money;
use RoundingMode;

class LhstpPaymentCalculator
{
    /**
     * Working scale for intermediate values. Keeps the 11/12 fraction exact
     * enough that the printed DPP equals the agreed examples.
     */
    private const SCALE = 12;

    /**
     * Calculate DPP LAIN, PPN, and Total Payment from the entered amount.
     *
     * Intermediate values stay unrounded until the total is computed; only
     * the total is rounded to whole rupiah, and the stored components use
     * currency precision.
     *
     * @return array{dpp_lain: string, ppn_amount: string, total_payment: string}
     */
    public function calculate(string $paymentAmount, string $ppnRate, string $ppnStatus): array
    {
        $amount = Money::scale($paymentAmount, 2);
        $rate = bcdiv(Money::scale($ppnRate, 4), '100', self::SCALE);

        // DPP LAIN is eleven twelfths of the taxable base.
        $elevenTwelfths = fn (string $value): string => bcdiv(bcmul($value, '11', self::SCALE), '12', self::SCALE);

        if ($ppnStatus === Contract::PPN_INCLUDED) {
            $gross = bcadd('1', bcdiv(bcmul($rate, '11', self::SCALE), '12', self::SCALE), self::SCALE);
            $dpp = $elevenTwelfths(bcdiv($amount, $gross, self::SCALE));
            $ppn = bcmul($dpp, $rate, self::SCALE);
            $total = bcround($amount, 0, RoundingMode::HalfAwayFromZero);
        } else {
            $dpp = $elevenTwelfths($amount);
            $ppn = bcmul($dpp, $rate, self::SCALE);
            $total = bcround(bcadd($amount, $ppn, self::SCALE), 0, RoundingMode::HalfAwayFromZero);
        }

        return [
            'dpp_lain' => bcround($dpp, 2, RoundingMode::HalfAwayFromZero),
            'ppn_amount' => bcround($ppn, 2, RoundingMode::HalfAwayFromZero),
            'total_payment' => bcadd($total, '0', 2),
        ];
    }
}
