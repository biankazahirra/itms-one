<?php

namespace App\Support;

final class TextNormalizer
{
    /**
     * Normalize a free-text label for duplicate comparison.
     *
     * Case, surrounding whitespace, and repeated inner whitespace are ignored.
     */
    public static function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));
    }
}
