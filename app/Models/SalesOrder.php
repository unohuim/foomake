<?php

namespace App\Models;

use App\Contracts\Workflows\Workflowable;
use App\Models\Concerns\HasTenantScope;
use App\Models\Concerns\HasNotes;
use App\Services\Workflows\SalesOrderWorkflow;
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
 * @property string|null $order_date
 * @property string $status
 */
class SalesOrder extends Model implements Workflowable
{
    use HasFactory;
    use HasNotes;
    use HasTenantScope;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_PACKING = 'PACKING';
    public const STATUS_PACKED = 'PACKED';
    public const STATUS_SHIPPING = 'SHIPPED';
    public const STATUS_INVOICED = 'INVOICED';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'contact_id',
        'order_date',
        'status',
        'external_source',
        'external_id',
        'external_status',
        'external_status_synced_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'order_date' => 'date:Y-m-d',
        'external_status_synced_at' => 'datetime',
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
            self::STATUS_PACKING,
            self::STATUS_PACKED,
            self::STATUS_SHIPPING,
            self::STATUS_INVOICED,
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
        return app(SalesOrderWorkflow::class)->availableTransitions($this);
    }

    /**
     * Return the workflow domain key for this record.
     */
    public function workflowDomainKey(): string
    {
        return 'sales';
    }

    /**
     * Return the record id used by generated workflow tasks.
     */
    public function workflowRecordId(): int
    {
        return (int) $this->id;
    }

    /**
     * Return the tenant id that owns this workflow record.
     */
    public function workflowTenantId(): int
    {
        return (int) $this->tenant_id;
    }

    /**
     * Return the persisted workflow status value.
     */
    public function workflowStatus(): string
    {
        return (string) $this->status;
    }

    /**
     * Set the persisted workflow status value.
     */
    public function setWorkflowStatus(string $status): void
    {
        $this->forceFill(['status' => $status]);
    }
}
