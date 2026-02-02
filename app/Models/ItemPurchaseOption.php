<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\ItemPurchaseOptionPrice;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ItemPurchaseOption extends Model
{
    use HasTenantScope;

    protected $guarded = [];

    /**
     * Get the tenant that owns the purchase option.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the supplier that provides this option.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the item associated with the purchase option.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the pack unit of measure.
     */
    public function packUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'pack_uom_id');
    }

    /**
     * Get the prices defined for this option.
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ItemPurchaseOptionPrice::class, 'item_purchase_option_id');
    }

    /**
     * Get the currently active price for this option.
     */
    public function currentPrice(): HasOne
    {
        return $this->hasOne(ItemPurchaseOptionPrice::class, 'item_purchase_option_id')
            ->whereNull('ended_at');
    }
}
