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
 * @property int $recipe_id
 * @property int|null $recipe_version_id
 * @property int $output_item_id
 * @property string $output_quantity
 * @property string $status
 * @property Carbon|null $due_date
 * @property int|null $workflow_stage_id
 * @property int|null $tasked_by_user_id
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $made_at
 * @property int|null $created_by_user_id
 * @property int|null $made_by_user_id
 */
class MakeOrder extends Model
{
    use HasTenantScope;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SCHEDULED = 'SCHEDULED';
    public const STATUS_MADE = 'MADE';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'tenant_id',
        'recipe_id',
        'recipe_version_id',
        'output_item_id',
        'output_quantity',
        'status',
        'due_date',
        'workflow_stage_id',
        'tasked_by_user_id',
        'scheduled_at',
        'made_at',
        'created_by_user_id',
        'made_by_user_id',
    ];

    protected $casts = [
        'output_quantity' => 'decimal:6',
        'due_date' => 'date',
        'scheduled_at' => 'datetime',
        'made_at' => 'datetime',
    ];

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
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * @return BelongsTo
     */
    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class);
    }

    /**
     * @return BelongsTo
     */
    public function outputItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'output_item_id');
    }

    /**
     * @return BelongsTo
     */
    public function workflowStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'workflow_stage_id');
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
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo
     */
    public function madeByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'made_by_user_id');
    }

    /**
     * @return MorphMany
     */
    public function stockMoves(): MorphMany
    {
        return $this->morphMany(StockMove::class, 'source');
    }

    /**
     * Snapshot lines used to execute the make order.
     *
     * @return HasMany
     */
    public function lines(): HasMany
    {
        return $this->hasMany(MakeOrderLine::class);
    }
}
