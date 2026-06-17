<?php

namespace App\Models;

use App\Contracts\Workflows\Workflowable;
use App\Models\Concerns\HasTenantScope;
use App\Models\Concerns\HasNotes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $created_by_user_id
 * @property int|null $tasked_by_user_id
 * @property int|null $assigned_to_user_id
 * @property string $name
 * @property Carbon $counted_at
 * @property int|null $workflow_stage_id
 * @property Carbon|null $posted_at
 * @property int|null $posted_by_user_id
 * @property Carbon|null $workflow_cancelled_at
 * @property int|null $workflow_cancelled_by_user_id
 * @property string|null $notes
 */
class InventoryCount extends Model implements Workflowable
{
    use HasNotes;
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'tasked_by_user_id',
        'assigned_to_user_id',
        'name',
        'counted_at',
        'workflow_stage_id',
        'posted_at',
        'posted_by_user_id',
        'workflow_cancelled_at',
        'workflow_cancelled_by_user_id',
        'notes',
    ];

    protected $casts = [
        'counted_at' => 'datetime',
        'posted_at' => 'datetime',
        'workflow_cancelled_at' => 'datetime',
    ];

    /**
     * @return BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCountLine::class);
    }

    /**
     * @return BelongsTo
     */
    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    /**
     * @return BelongsTo
     */
    public function workflowCancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'workflow_cancelled_by_user_id');
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
    public function taskedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tasked_by_user_id');
    }

    /**
     * @return BelongsTo
     */
    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * @return BelongsTo
     */
    public function workflowStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'workflow_stage_id');
    }

    /**
     * Return the workflow domain key for this inventory count.
     */
    public function workflowDomainKey(): string
    {
        return 'inventory';
    }

    /**
     * Return the workflow task domain record id.
     */
    public function workflowRecordId(): int
    {
        return (int) $this->id;
    }

    /**
     * Return the owning tenant id.
     */
    public function workflowTenantId(): int
    {
        return (int) $this->tenant_id;
    }

    /**
     * Return the workflow-facing status value.
     */
    public function workflowStatus(): string
    {
        return $this->getStatusAttribute();
    }

    /**
     * Inventory Count workflow status is derived from fields owned by the domain.
     */
    public function setWorkflowStatus(string $status): void
    {
    }

    /**
     * @return MorphMany
     */
    public function stockMoves(): MorphMany
    {
        return $this->morphMany(StockMove::class, 'source');
    }

    /**
     * @return string
     */
    public function getStatusAttribute(): string
    {
        if ($this->workflow_cancelled_at !== null) {
            return 'cancelled';
        }

        return $this->posted_at === null ? 'draft' : 'posted';
    }
}
