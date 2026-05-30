<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * Class WorkflowStage
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $workflow_domain_id
 * @property string $key
 * @property string $name
 * @property string $action_verb
 * @property string $status_complete_label
 * @property string $completion_mode
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_core
 * @property bool $is_inventory_effect_stage
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
        'action_verb',
        'status_complete_label',
        'completion_mode',
        'description',
        'sort_order',
        'is_active',
        'is_core',
        'is_inventory_effect_stage',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_core' => 'boolean',
        'is_inventory_effect_stage' => 'boolean',
    ];

    /**
     * Ensure workflow stage writes receive safe workflow defaults.
     */
    protected static function booted(): void
    {
        static::saving(function (WorkflowStage $workflowStage): void {
            if (
                Schema::hasColumn('workflow_stages', 'action_verb')
                && trim((string) $workflowStage->action_verb) === ''
            ) {
                $workflowStage->action_verb = (string) $workflowStage->name;
            }

            if (
                Schema::hasColumn('workflow_stages', 'status_complete_label')
                && trim((string) $workflowStage->status_complete_label) === ''
            ) {
                $workflowStage->status_complete_label = (string) $workflowStage->name;
            }

            if (
                Schema::hasColumn('workflow_stages', 'completion_mode')
                && trim((string) $workflowStage->completion_mode) === ''
            ) {
                $workflowStage->completion_mode = 'manual';
            }
        });
    }

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
