<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $created_by_user_id
 * @property int|null $supplier_id
 * @property Carbon|null $order_date
 * @property int|null $shipping_cents
 * @property int|null $tax_cents
 * @property int $po_subtotal_cents
 * @property int $po_grand_total_cents
 * @property string|null $po_number
 * @property string|null $notes
 * @property string $status
 */
class PurchaseOrder extends Model
{
    use HasTenantScope;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_PARTIALLY_RECEIVED = 'PARTIALLY-RECEIVED';
    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_BACK_ORDERED = 'BACK-ORDERED';
    public const STATUS_SHORT_CLOSED = 'SHORT-CLOSED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'order_date' => 'date:Y-m-d',
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
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /**
     * @return HasMany
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceipt::class);
    }

    /**
     * @return HasMany
     */
    public function shortClosures(): HasMany
    {
        return $this->hasMany(PurchaseOrderShortClosure::class);
    }
}
