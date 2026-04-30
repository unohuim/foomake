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
        $numericValue = (float) ($quantity ?? '0');

        return number_format($numericValue, $normalizedPrecision, '.', '');
    }

    public static function formatForUom(string|int|float|null $quantity, ?Uom $uom, int $fallbackPrecision = 6): string
    {
        $precision = $uom?->display_precision ?? $fallbackPrecision;

        return self::format($quantity, (int) $precision);
    }
}
