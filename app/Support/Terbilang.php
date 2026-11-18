<?php

namespace App\Support;

final class Terbilang
{
    private const UNITS = [
        '', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan',
        'Sembilan', 'Sepuluh', 'Sebelas',
    ];

    /**
     * Spell an already rounded rupiah amount in Indonesian words.
     */
    public static function rupiah(string|int|float $amount): string
    {
        $whole = (int) bcadd((string) $amount, '0', 0);

        return trim(self::words($whole)).' Rupiah';
    }

    private static function words(int $number): string
    {
        if ($number === 0) {
            return 'Nol';
        }

        if ($number < 12) {
            return self::UNITS[$number];
        }

        if ($number < 20) {
            return self::words($number - 10).' Belas';
        }

        if ($number < 100) {
            return self::words(intdiv($number, 10)).' Puluh'.self::rest($number, 10);
        }

        if ($number < 200) {
            return 'Seratus'.self::rest($number, 100);
        }

        if ($number < 1000) {
            return self::words(intdiv($number, 100)).' Ratus'.self::rest($number, 100);
        }

        if ($number < 2000) {
            return 'Seribu'.self::rest($number, 1000);
        }

        if ($number < 1_000_000) {
            return self::words(intdiv($number, 1000)).' Ribu'.self::rest($number, 1000);
        }

        if ($number < 1_000_000_000) {
            return self::words(intdiv($number, 1_000_000)).' Juta'.self::rest($number, 1_000_000);
        }

        if ($number < 1_000_000_000_000) {
            return self::words(intdiv($number, 1_000_000_000)).' Miliar'.self::rest($number, 1_000_000_000);
        }

        return self::words(intdiv($number, 1_000_000_000_000)).' Triliun'.self::rest($number, 1_000_000_000_000);
    }

    private static function rest(int $number, int $divisor): string
    {
        $remainder = $number % $divisor;

        return $remainder === 0 ? '' : ' '.self::words($remainder);
    }
}
