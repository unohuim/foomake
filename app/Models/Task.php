<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Task
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $workflow_domain_id
 * @property int $domain_record_id
 * @property int $workflow_stage_id
 * @property int|null $workflow_task_template_id
 * @property int $assigned_to_user_id
 * @property string $title
 * @property string|null $description
 * @property int $sort_order
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int|null $completed_by_user_id
 */
class Task extends Model
{
    use HasFactory;
    use HasTenantScope;

    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'workflow_domain_id',
        'domain_record_id',
        'workflow_stage_id',
        'workflow_task_template_id',
        'assigned_to_user_id',
        'title',
        'description',
        'sort_order',
        'status',
        'completed_at',
        'completed_by_user_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'completed_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the task.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the workflow domain for the task.
     */
    public function workflowDomain(): BelongsTo
    {
        return $this->belongsTo(WorkflowDomain::class);
    }

    /**
     * Get the workflow stage for the task.
     */
    public function workflowStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class);
    }

    /**
     * Get the originating workflow task template.
     */
    public function workflowTaskTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkflowTaskTemplate::class);
    }

    /**
     * Get the assigned user for the task.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Get the user who completed the task.
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    /**
     * Determine whether the task is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}

