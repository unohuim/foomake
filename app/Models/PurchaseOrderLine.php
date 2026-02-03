<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $purchase_order_id
 * @property int $item_id
 * @property int $item_purchase_option_id
 * @property int $pack_count
 * @property int $unit_price_cents
 * @property int $line_subtotal_cents
 * @property int $unit_price_amount
 * @property string $unit_price_currency_code
 * @property int $converted_unit_price_amount
 * @property string $fx_rate
 * @property Carbon $fx_rate_as_of
 */
class PurchaseOrderLine extends Model
{
    use HasTenantScope;

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'fx_rate' => 'string',
        'fx_rate_as_of' => 'date',
    ];

    public function getFxRateAttribute($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = rtrim(rtrim((string) $value, '0'), '.');

        if ($trimmed === '') {
            return '0';
        }

        return $trimmed;
    }

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
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo
     */
    public function purchaseOption(): BelongsTo
    {
        return $this->belongsTo(ItemPurchaseOption::class, 'item_purchase_option_id');
    }
}
