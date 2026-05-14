<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class SalesOrderLine
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $sales_order_id
 * @property int $item_id
 * @property string|null $external_id
 * @property string $quantity
 * @property int $unit_price_cents
 * @property string $unit_price_currency_code
 * @property string $line_total_cents
 */
class SalesOrderLine extends Model
{
    use HasFactory;
    use HasTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'sales_order_id',
        'item_id',
        'external_id',
        'quantity',
        'unit_price_cents',
        'unit_price_currency_code',
        'line_total_cents',
    ];

    /**
     * Get the sales order that owns the line.
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * Get the item assigned to the line.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the line quantity as a canonical scale-6 string.
     */
    protected function quantity(): Attribute
    {
        return Attribute::make(
            get: fn ($value): ?string => $this->normalizeScaleSix($value),
        );
    }

    /**
     * Get the line total in minor currency units as a canonical scale-6 string.
     */
    protected function lineTotalCents(): Attribute
    {
        return Attribute::make(
            get: fn ($value): ?string => $this->normalizeScaleSix($value),
        );
    }

    /**
     * Normalize a BCMath-compatible numeric value to the canonical scale.
     */
    private function normalizeScaleSix(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return bcadd((string) $value, '0', 6);
    }
}
