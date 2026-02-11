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
 * @property int $purchase_order_id
 * @property Carbon $received_at
 * @property int $received_by_user_id
 * @property string|null $reference
 * @property string|null $notes
 */
class PurchaseOrderReceipt extends Model
{
    use HasTenantScope;

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'received_at' => 'datetime',
    ];

    /**
     * @return BelongsTo
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo
     */
    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    /**
     * @return HasMany
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceiptLine::class);
    }
}
