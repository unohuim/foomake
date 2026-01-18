<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Item
 *
 * Represents a tenant-owned inventory identity.
 */
class Item extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'name',
        'base_uom_id',
    ];

    /**
     * @return BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo
     */
    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'base_uom_id');
    }

    /**
     * @return HasMany
     */
    public function itemUomConversions(): HasMany
    {
        return $this->hasMany(ItemUomConversion::class);
    }

    /**
     * Look up an item-specific conversion factor between two UoMs.
     *
     * @param int $fromUomId
     * @param int $toUomId
     * @return string|null
     */
    public function getItemUomConversionFactor(int $fromUomId, int $toUomId): ?string
    {
        return $this->itemUomConversions()
            ->where('from_uom_id', $fromUomId)
            ->where('to_uom_id', $toUomId)
            ->value('conversion_factor');
    }
}
