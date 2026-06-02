<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Concerns\HasNotes;
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
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by_user_id
 * @property Carbon|null $back_ordered_at
 * @property int|null $back_ordered_by_user_id
 * @property int|null $current_workflow_stage_id
 * @property int|null $last_completed_workflow_stage_id
 * @property Carbon|null $workflow_cancelled_at
 */
class PurchaseOrder extends Model
{
    use HasNotes;
    use HasTenantScope;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_CREATED = 'CREATED';
    public const STATUS_PARTIALLY_RECEIVED = 'PARTIALLY_RECEIVED';
    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const ACTION_CANCEL = 'cancel';
    public const ACTION_BACK_ORDER = 'back_order';

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_CREATED,
            self::STATUS_PARTIALLY_RECEIVED,
            self::STATUS_RECEIVED,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'order_date' => 'date:Y-m-d',
        'cancelled_at' => 'datetime',
        'back_ordered_at' => 'datetime',
        'workflow_cancelled_at' => 'datetime',
    ];

    /**
     * Determine whether the order is marked as cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->workflow_cancelled_at !== null || $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Determine whether the order is terminal.
     */
    public function isTerminal(): bool
    {
        return $this->status === self::STATUS_COMPLETED || $this->isCancelled();
    }

    /**
     * Determine whether receiving-stage actions are allowed.
     */
    public function isReceivingStage(): bool
    {
        return in_array($this->status, [
            self::STATUS_CREATED,
            self::STATUS_PARTIALLY_RECEIVED,
        ], true) && ! $this->isCancelled();
    }

    /**
     * Derive the workflow-facing status from workflow state.
     */
    public function workflowStatus(): string
    {
        if ($this->workflow_cancelled_at !== null) {
            return self::STATUS_CANCELLED;
        }

        if ($this->last_completed_workflow_stage_id === null) {
            return $this->status === self::STATUS_CANCELLED
                ? self::STATUS_CANCELLED
                : self::STATUS_DRAFT;
        }

        if ($this->status === self::STATUS_PARTIALLY_RECEIVED) {
            return self::STATUS_PARTIALLY_RECEIVED;
        }

        $completedLabel = $this->lastCompletedWorkflowStage?->status_complete_label;

        if ($completedLabel) {
            return (string) $completedLabel;
        }

        return self::STATUS_DRAFT;
    }

    /**
     * Determine whether purchase order header and line edits are currently allowed.
     */
    public function isWorkflowEditable(): bool
    {
        if ($this->workflow_cancelled_at !== null) {
            return false;
        }

        if ($this->current_workflow_stage_id === null && $this->last_completed_workflow_stage_id !== null) {
            return false;
        }

        $this->loadMissing('lastCompletedWorkflowStage');

        $lastCompletedStage = $this->lastCompletedWorkflowStage;

        if (! $lastCompletedStage) {
            return true;
        }

        $inventoryStage = WorkflowStage::query()
            ->where('tenant_id', $this->tenant_id)
            ->where('workflow_domain_id', $lastCompletedStage->workflow_domain_id)
            ->where('is_active', true)
            ->where('is_inventory_effect_stage', true)
            ->orderBy('sort_order')
            ->first();

        if (! $inventoryStage) {
            return true;
        }

        return (int) $lastCompletedStage->sort_order < (int) $inventoryStage->sort_order;
    }

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
     * @return BelongsTo
     */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /**
     * @return BelongsTo
     */
    public function backOrderedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'back_ordered_by_user_id');
    }

    /**
     * Get the current workflow stage for the purchase order.
     */
    public function currentWorkflowStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'current_workflow_stage_id');
    }

    /**
     * Get the last completed workflow stage for the purchase order.
     */
    public function lastCompletedWorkflowStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'last_completed_workflow_stage_id');
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
