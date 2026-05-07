<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class WorkflowStage
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $workflow_domain_id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 */
class WorkflowStage extends Model
{
    use HasFactory;
    use HasTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'workflow_domain_id',
        'key',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the tenant that owns the workflow stage.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the workflow domain for the stage.
     */
    public function workflowDomain(): BelongsTo
    {
        return $this->belongsTo(WorkflowDomain::class);
    }

    /**
     * Get the task templates configured for the stage.
     */
    public function taskTemplates(): HasMany
    {
        return $this->hasMany(WorkflowTaskTemplate::class);
    }

    /**
     * Get the tasks generated for the stage.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}

