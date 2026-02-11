<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $purchase_order_short_closure_id
 * @property int $purchase_order_line_id
 * @property string $short_closed_quantity
 */
class PurchaseOrderShortClosureLine extends Model
{
    use HasTenantScope;

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'short_closed_quantity' => 'decimal:6',
    ];

    /**
     * @return BelongsTo
     */
    public function shortClosure(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderShortClosure::class, 'purchase_order_short_closure_id');
    }

    /**
     * @return BelongsTo
     */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}
