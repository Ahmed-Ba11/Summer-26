<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function toHalalas(int|float|string $amount): int
    {
        if (is_float($amount)) {
            if (! is_finite($amount)) {
                throw new InvalidArgumentException('Money amount must be finite.');
            }

            $normalized = rtrim(rtrim(sprintf('%.14F', $amount), '0'), '.');
        } else {
            $normalized = trim((string) $amount);
        }

        if (! preg_match('/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)$/', $normalized)) {
            throw new InvalidArgumentException('Money amount must be a decimal number with at most two decimal places.');
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = rtrim($fraction, '0');

        if (strlen($fraction) > 2) {
            throw new InvalidArgumentException('Money amount must have at most two decimal places.');
        }

        $whole = ltrim($whole, '0') ?: '0';
        $fraction = str_pad($fraction, 2, '0');
        $wholeLimit = intdiv(PHP_INT_MAX, 100);
        $fractionLimit = PHP_INT_MAX % 100;

        if (strlen($whole) > strlen((string) $wholeLimit)
            || (strlen($whole) === strlen((string) $wholeLimit) && $whole > (string) $wholeLimit)
            || ((int) $whole === $wholeLimit && (int) $fraction > $fractionLimit)) {
            throw new InvalidArgumentException('Money amount is outside the supported range.');
        }

        $result = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$result : $result;
    }
}
