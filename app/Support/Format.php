<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

final class Format
{
    /**
     * Format a stored decimal value as Indonesian Rupiah for display only.
     *
     * Never use the formatted string as a calculation input.
     */
    public static function rupiah(string|int|float|null $amount, int $decimals = 0): string
    {
        return 'Rp '.self::angka($amount, $decimals);
    }

    /**
     * Format a stored decimal value as a grouped number without the currency
     * prefix, for printed documents that already contain "Rp".
     */
    public static function angka(string|int|float|null $amount, int $decimals = 0): string
    {
        $value = is_numeric($amount) ? (float) $amount : 0.0;

        return number_format($value, $decimals, ',', '.');
    }

    /**
     * Format a date for Indonesian user-facing display.
     */
    public static function tanggal(DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        return Carbon::parse($date)->locale('id')->translatedFormat('d F Y');
    }

    /**
     * Format a date and time for Indonesian user-facing display.
     */
    public static function tanggalWaktu(DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        return Carbon::parse($date)->locale('id')->translatedFormat('d F Y, H:i');
    }
}
