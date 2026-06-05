<?php

namespace App\Models;

use App\Actions\Inventory\CalculateItemOnHandQuantityAction;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'is_active',
        'is_purchasable',
        'is_sellable',
        'is_manufacturable',
        'is_stockable',
        'default_price_cents',
        'default_price_currency_code',
        'image_url',
        'external_source',
        'external_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_purchasable' => 'boolean',
        'is_sellable' => 'boolean',
        'is_manufacturable' => 'boolean',
        'is_stockable' => 'boolean',
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
     * @return HasMany
     */
    public function stockMoves(): HasMany
    {
        return $this->hasMany(StockMove::class);
    }

    /**
     * @return HasMany
     */
    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    /**
     * @return HasMany
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    /**
     * Get the sales order lines using the item.
     */
    public function salesOrderLines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    /**
     * @return HasOne
     */
    public function activeRecipe(): HasOne
    {
        return $this->hasOne(Recipe::class)->where('is_active', true);
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

    /**
     * Get the on-hand quantity derived from the stock move ledger.
     *
     * @return string
     */
    public function onHandQuantity(): string
    {
        return app(CalculateItemOnHandQuantityAction::class)->execute($this);
    }
}
