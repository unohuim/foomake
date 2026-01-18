<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Class ItemUomConversion
 *
 * Represents an item-specific unit conversion.
 */
class ItemUomConversion extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'item_id',
        'from_uom_id',
        'to_uom_id',
        'conversion_factor',
    ];

    /**
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(function (ItemUomConversion $conversion): void {
            if ($conversion->conversion_factor <= 0) {
                throw new InvalidArgumentException('Conversion factor must be greater than zero.');
            }
        });
    }

    /**
     * @return BelongsTo
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
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
