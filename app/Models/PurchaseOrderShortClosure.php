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
 * @property Carbon $short_closed_at
 * @property int $short_closed_by_user_id
 * @property string|null $reference
 * @property string|null $notes
 */
class PurchaseOrderShortClosure extends Model
{
    use HasTenantScope;

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'short_closed_at' => 'datetime',
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
    public function shortClosedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'short_closed_by_user_id');
    }

    /**
     * @return HasMany
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderShortClosureLine::class);
    }
}
