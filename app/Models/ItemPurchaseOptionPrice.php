<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class ItemPurchaseOptionPrice
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $item_purchase_option_id
 * @property int $price_cents
 * @property string $price_currency_code
 * @property int $converted_price_cents
 * @property string $fx_rate
 * @property string $fx_rate_as_of
 * @property \Illuminate\Support\Carbon $effective_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 */
class ItemPurchaseOptionPrice extends Model
{
    use HasTenantScope;

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'fx_rate' => 'string',
        'fx_rate_as_of' => 'date',
        'effective_at' => 'datetime',
        'ended_at' => 'datetime',
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
     * Get the purchase option this price belongs to.
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ItemPurchaseOption::class, 'item_purchase_option_id');
    }
}
