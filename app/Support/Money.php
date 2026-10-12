<?php

namespace App\Support;

/**
 * Integer-cent arithmetic so totals never accumulate float rounding errors.
 */
class Money
{
    public static function toCents(string|int|float $amount): int
    {
        $normalized = number_format((float) $amount, 2, '.', '');
        [$whole, $fraction] = explode('.', ltrim($normalized, '-'));
        $cents = (int) $whole * 100 + (int) $fraction;

        return str_starts_with($normalized, '-') ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
