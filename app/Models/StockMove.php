<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

/**
 * Class StockMove
 *
 * Represents an append-only inventory ledger entry.
 */
class StockMove extends Model
{
    use HasTenantScope;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'item_id',
        'uom_id',
        'quantity',
        'type',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockMove $stockMove): void {
            $item = $stockMove->item ?? Item::find($stockMove->item_id);

            if ($item && (int) $stockMove->uom_id !== (int) $item->base_uom_id) {
                throw new InvalidArgumentException('Stock move UoM must match item base UoM.');
            }
        });
    }

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

    /**
     * @return MorphTo
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
