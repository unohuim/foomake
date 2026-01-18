<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
