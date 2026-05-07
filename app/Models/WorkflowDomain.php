<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class WorkflowDomain
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property int $sort_order
 */
class WorkflowDomain extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
        'sort_order',
    ];

    /**
     * Get the stages for the workflow domain.
     */
    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class);
    }

    /**
     * Get the task templates for the workflow domain.
     */
    public function taskTemplates(): HasMany
    {
        return $this->hasMany(WorkflowTaskTemplate::class);
    }

    /**
     * Get the tasks for the workflow domain.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}

