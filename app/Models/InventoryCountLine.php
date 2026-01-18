<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $inventory_count_id
 * @property int $item_id
 * @property string $counted_quantity
 * @property string|null $notes
 */
class InventoryCountLine extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'inventory_count_id',
        'item_id',
        'counted_quantity',
        'notes',
    ];

    protected $casts = [
        'counted_quantity' => 'decimal:6',
    ];

    /**
     * @return BelongsTo
     */
    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class);
    }

    /**
     * @return BelongsTo
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
