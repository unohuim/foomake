<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class WorkflowTaskTemplate
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $workflow_domain_id
 * @property int $workflow_stage_id
 * @property string $title
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property int|null $default_assignee_user_id
 */
class WorkflowTaskTemplate extends Model
{
    use HasFactory;
    use HasTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'workflow_domain_id',
        'workflow_stage_id',
        'title',
        'description',
        'sort_order',
        'is_active',
        'default_assignee_user_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the tenant that owns the task template.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the domain that owns the task template.
     */
    public function workflowDomain(): BelongsTo
    {
        return $this->belongsTo(WorkflowDomain::class);
    }

    /**
     * Get the stage that owns the task template.
     */
    public function workflowStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class);
    }

    /**
     * Get the default assignee for the task template.
     */
    public function defaultAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assignee_user_id');
    }

    /**
     * Get the generated tasks created from the template.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}

