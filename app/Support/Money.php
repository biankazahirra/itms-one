<?php

namespace App\Support;

use RoundingMode;

final class Money
{
    /**
     * Multiply quantity by unit price and round the line total to 2 decimals.
     */
    public static function lineTotal(string $quantity, string $unitPrice): string
    {
        return bcround(bcmul($quantity, $unitPrice, 8), 2, RoundingMode::HalfAwayFromZero);
    }

    /**
     * Sum stored decimal strings without binary floating point.
     *
     * @param  array<int, string>  $values
     */
    public static function sum(array $values): string
    {
        $total = '0';

        foreach ($values as $value) {
            $total = bcadd($total, $value, 2);
        }

        return $total;
    }

    /**
     * Normalize a validated numeric string to a fixed decimal scale.
     */
    public static function scale(string|int|float $value, int $scale): string
    {
        return bcadd((string) $value, '0', $scale);
    }

    /**
     * Display a quantity without trailing zeros.
     */
    public static function quantity(string|int|float|null $value): string
    {
        $formatted = rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');

        return $formatted === '' ? '0' : $formatted;
    }
}
