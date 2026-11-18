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
        $base = $this->paymentBeforePpnRaw($paymentAmount, $ppnRate, $ppnStatus);
        $rate = bcdiv(Money::scale($ppnRate, 4), '100', self::SCALE);

        // DPP LAIN is eleven twelfths of the taxable base.
        $elevenTwelfths = fn (string $value): string => bcdiv(bcmul($value, '11', self::SCALE), '12', self::SCALE);

        if ($ppnStatus === Contract::PPN_INCLUDED) {
            $dpp = $elevenTwelfths($base);
            $ppn = bcmul($dpp, $rate, self::SCALE);
            $total = bcround($amount, 0, RoundingMode::HalfAwayFromZero);
        } else {
            $dpp = $elevenTwelfths($base);
            $ppn = bcmul($dpp, $rate, self::SCALE);
            $total = bcround(bcadd($base, $ppn, self::SCALE), 0, RoundingMode::HalfAwayFromZero);
        }

        return [
            'dpp_lain' => bcround($dpp, 2, RoundingMode::HalfAwayFromZero),
            'ppn_amount' => bcround($ppn, 2, RoundingMode::HalfAwayFromZero),
            'total_payment' => bcadd($total, '0', 2),
        ];
    }

    /**
     * Recover the payment value before PPN when the entered amount is the
     * final payable amount (contract already includes PPN).
     *
     * The printed chain is: base x 11/12 = DPP, DPP x rate = PPN, and
     * base + PPN = the original entered amount.
     */
    public function paymentBeforePpn(string $paymentAmount, string $ppnRate, string $ppnStatus): string
    {
        return bcround($this->paymentBeforePpnRaw($paymentAmount, $ppnRate, $ppnStatus), 2, RoundingMode::HalfAwayFromZero);
    }

    private function paymentBeforePpnRaw(string $paymentAmount, string $ppnRate, string $ppnStatus): string
    {
        $amount = Money::scale($paymentAmount, 2);

        if ($ppnStatus !== Contract::PPN_INCLUDED) {
            return $amount;
        }

        $rate = bcdiv(Money::scale($ppnRate, 4), '100', self::SCALE);
        $gross = bcadd('1', bcdiv(bcmul($rate, '11', self::SCALE), '12', self::SCALE), self::SCALE);

        return bcdiv($amount, $gross, self::SCALE);
    }
}
