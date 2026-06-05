<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a derived inventory balance for one item and UoM.
 */
class InventoryBalance extends Model
{
    use HasTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'item_id',
        'uom_id',
        'quantity',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'decimal:6',
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
}
