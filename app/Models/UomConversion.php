<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Class UomConversion
 *
 * Represents a conversion between UoMs within the same category.
 */
class UomConversion extends Model
{
    protected $fillable = [
        'from_uom_id',
        'to_uom_id',
        'multiplier',
    ];

    protected $casts = [
        'multiplier' => 'decimal:8',
    ];

    /**
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(function (UomConversion $conversion): void {
            $fromCategoryId = Uom::query()
                ->whereKey($conversion->from_uom_id)
                ->value('uom_category_id');

            $toCategoryId = Uom::query()
                ->whereKey($conversion->to_uom_id)
                ->value('uom_category_id');

            if ($fromCategoryId === null || $toCategoryId === null) {
                return;
            }

            if ($fromCategoryId !== $toCategoryId) {
                throw new InvalidArgumentException('UoM conversions must stay within the same category.');
            }
        });
    }

    /**
     * @return BelongsTo
     */
    public function fromUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'from_uom_id');
    }

    /**
     * @return BelongsTo
     */
    public function toUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'to_uom_id');
    }
}
