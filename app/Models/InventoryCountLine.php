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
 * @property int|null $uom_id
 * @property string|null $counted_quantity
 * @property string|null $notes
 */
class InventoryCountLine extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'inventory_count_id',
        'item_id',
        'uom_id',
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

    /**
     * @return BelongsTo
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    /**
     * Resolve the counted UoM snapshot, including global system UoMs.
     */
    public function snapshotUom(): ?Uom
    {
        if ($this->uom_id === null) {
            return null;
        }

        return Uom::withoutGlobalScopes()
            ->whereKey($this->uom_id)
            ->where(function ($query): void {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', $this->tenant_id);
            })
            ->first();
    }
}
