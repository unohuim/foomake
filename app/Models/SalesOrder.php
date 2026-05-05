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
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

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
            self::STATUS_OPEN,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Return statuses that may still be edited.
     *
     * @return list<string>
     */
    public static function editableStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_OPEN,
        ];
    }

    /**
     * Return terminal statuses.
     *
     * @return list<string>
     */
    public static function terminalStatuses(): array
    {
        return [
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Return the manual lifecycle transition map.
     *
     * @return array<string, list<string>>
     */
    public static function transitionMap(): array
    {
        return [
            self::STATUS_DRAFT => [
                self::STATUS_OPEN,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_OPEN => [
                self::STATUS_COMPLETED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => [],
        ];
    }

    /**
     * Determine whether header fields may still be edited.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, self::editableStatuses(), true);
    }

    /**
     * Determine whether line mutations are allowed.
     */
    public function allowsLineMutations(): bool
    {
        return $this->isEditable();
    }

    /**
     * Determine whether a target status transition is allowed.
     */
    public function canTransitionTo(string $targetStatus): bool
    {
        return in_array($targetStatus, $this->availableTransitions(), true);
    }

    /**
     * Return available target statuses from the current status.
     *
     * @return list<string>
     */
    public function availableTransitions(): array
    {
        return self::transitionMap()[$this->status] ?? [];
    }
}
