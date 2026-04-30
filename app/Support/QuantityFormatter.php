<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Uom;

class QuantityFormatter
{
    public const MIN_PRECISION = 0;
    public const MAX_PRECISION = 6;

    public static function format(string|int|float|null $quantity, int $precision): string
    {
        $normalizedPrecision = max(self::MIN_PRECISION, min(self::MAX_PRECISION, $precision));
        $normalizedValue = self::normalizeNumericString($quantity);

        return self::roundHalfUp($normalizedValue, $normalizedPrecision);
    }

    public static function formatForUom(string|int|float|null $quantity, ?Uom $uom, int $fallbackPrecision = 6): string
    {
        $precision = $uom?->display_precision ?? $fallbackPrecision;

        return self::format($quantity, (int) $precision);
    }

    private static function normalizeNumericString(string|int|float|null $quantity): string
    {
        if ($quantity === null) {
            return '0';
        }

        if (is_int($quantity)) {
            return (string) $quantity;
        }

        if (is_float($quantity)) {
            return self::trimTrailingZeros(sprintf('%.14F', $quantity));
        }

        $trimmed = trim($quantity);

        if ($trimmed === '' || ! preg_match('/^-?\d+(?:\.\d+)?$/', $trimmed)) {
            return '0';
        }

        return $trimmed;
    }

    private static function roundHalfUp(string $quantity, int $precision): string
    {
        $isNegative = str_starts_with($quantity, '-');
        $absolute = $isNegative ? substr($quantity, 1) : $quantity;
        [$wholePart, $fractionPart] = array_pad(explode('.', $absolute, 2), 2, '');
        $fractionPart = preg_replace('/\D/', '', $fractionPart) ?? '';
        $fractionPart = str_pad($fractionPart, $precision + 1, '0');
        $retainedFraction = substr($fractionPart, 0, $precision);
        $roundDigit = (int) substr($fractionPart, $precision, 1);

        $rounded = $precision === 0
            ? $wholePart
            : $wholePart . '.' . $retainedFraction;

        if ($roundDigit >= 5) {
            $increment = $precision === 0
                ? '1'
                : '0.' . str_repeat('0', max(0, $precision - 1)) . '1';
            $rounded = bcadd($rounded, $increment, $precision);
        }

        if (bccomp($rounded, '0', $precision) === 0) {
            return $rounded;
        }

        return $isNegative ? '-' . $rounded : $rounded;
    }

    private static function trimTrailingZeros(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value;
        }

        $trimmed = rtrim(rtrim($value, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
