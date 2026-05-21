<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
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
 * @property Carbon $counted_at
 * @property int|null $workflow_stage_id
 * @property Carbon|null $posted_at
 * @property int|null $posted_by_user_id
 * @property string|null $notes
 */
class InventoryCount extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'tasked_by_user_id',
        'assigned_to_user_id',
        'counted_at',
        'workflow_stage_id',
        'posted_at',
        'posted_by_user_id',
        'notes',
    ];

    protected $casts = [
        'counted_at' => 'datetime',
        'posted_at' => 'datetime',
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
        return $this->posted_at === null ? 'draft' : 'posted';
    }
}
