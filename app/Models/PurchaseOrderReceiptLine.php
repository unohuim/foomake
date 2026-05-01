<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $purchase_order_receipt_id
 * @property int $purchase_order_line_id
 * @property int|null $stock_move_id
 * @property string $received_quantity
 */
class PurchaseOrderReceiptLine extends Model
{
    use HasTenantScope;

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'received_quantity' => 'decimal:6',
    ];

    /**
     * @return BelongsTo
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderReceipt::class, 'purchase_order_receipt_id');
    }

    /**
     * @return BelongsTo
     */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /**
     * @return BelongsTo
     */
    public function stockMove(): BelongsTo
    {
        return $this->belongsTo(StockMove::class);
    }
}
