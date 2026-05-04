<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class SalesOrder
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $customer_id
 * @property int|null $contact_id
 * @property string $status
 */
class SalesOrder extends Model
{
    use HasFactory;
    use HasTenantScope;

    public const STATUS_DRAFT = 'DRAFT';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'contact_id',
        'status',
    ];

    /**
     * Get the tenant that owns the sales order.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the customer assigned to the sales order.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the selected contact for the sales order.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CustomerContact::class, 'contact_id');
    }

    /**
     * Get the lines attached to the sales order.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class)->orderBy('created_at');
    }

    /**
     * Return the valid sales order statuses for this PR slice.
     *
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
        ];
    }
}
