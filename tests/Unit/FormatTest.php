<?php

use App\Support\Format;
use App\Support\TextNormalizer;

it('formats rupiah values for display', function () {
    expect(Format::rupiah(0))->toBe('Rp 0')
        ->and(Format::rupiah(1500000))->toBe('Rp 1.500.000')
        ->and(Format::rupiah('1234567.89', 2))->toBe('Rp 1.234.567,89')
        ->and(Format::rupiah(null))->toBe('Rp 0');
});

it('formats dates in indonesian', function () {
    expect(Format::tanggal('2026-11-17'))->toBe('17 November 2026')
        ->and(Format::tanggal(null))->toBe('—')
        ->and(Format::tanggalWaktu('2026-11-17 09:05:00'))->toBe('17 November 2026, 09:05');
});

it('normalizes text for duplicate comparison', function () {
    expect(TextNormalizer::normalize('  PT   Maju  Bersama '))
        ->toBe('pt maju bersama')
        ->and(TextNormalizer::normalize(null))->toBe('');
});
