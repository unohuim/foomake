<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Concerns\HasNotes;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property string $runs
 * @property string $expected_output_qty
 * @property string|null $actual_output_qty
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
    use HasNotes;
    use HasTenantScope;

    private const QUANTITY_SCALE = 6;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SCHEDULED = 'SCHEDULED';
    public const STATUS_MADE = 'MADE';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'tenant_id',
        'recipe_id',
        'recipe_version_id',
        'output_item_id',
        'runs',
        'expected_output_qty',
        'actual_output_qty',
        'output_quantity',
        'actual_output_quantity',
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
        'runs' => 'decimal:6',
        'expected_output_qty' => 'decimal:6',
        'actual_output_qty' => 'decimal:6',
        'output_quantity' => 'decimal:6',
        'actual_output_quantity' => 'decimal:6',
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

    /**
     * Backward-compatible alias for the legacy make_orders.output_quantity column.
     */
    protected function outputQuantity(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => $this->canonicalQuantity($attributes['runs'] ?? $value ?? '0.000000'),
            set: fn (mixed $value): array => [
                'output_quantity' => $this->canonicalQuantity($value ?? '0.000000'),
                'runs' => $this->canonicalQuantity($value ?? '0.000000'),
            ],
        );
    }

    /**
     * Canonical runs attribute with legacy column synchronization during rollout.
     */
    protected function runs(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => $this->canonicalQuantity($value ?? $attributes['output_quantity'] ?? '0.000000'),
            set: fn (mixed $value): array => [
                'runs' => $this->canonicalQuantity($value ?? '0.000000'),
                'output_quantity' => $this->canonicalQuantity($value ?? '0.000000'),
            ],
        );
    }

    /**
     * Backward-compatible alias for the legacy make_orders.actual_output_quantity column.
     */
    protected function actualOutputQuantity(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): ?string => ($attributes['actual_output_qty'] ?? $value) === null
                ? null
                : $this->canonicalQuantity($attributes['actual_output_qty'] ?? $value),
            set: fn (mixed $value): array => [
                'actual_output_quantity' => $value === null ? null : $this->canonicalQuantity($value),
                'actual_output_qty' => $value === null ? null : $this->canonicalQuantity($value),
            ],
        );
    }

    /**
     * Canonical actual output attribute with legacy column synchronization during rollout.
     */
    protected function actualOutputQty(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): ?string => ($value ?? $attributes['actual_output_quantity'] ?? null) === null
                ? null
                : $this->canonicalQuantity($value ?? $attributes['actual_output_quantity'] ?? null),
            set: fn (mixed $value): array => [
                'actual_output_qty' => $value === null ? null : $this->canonicalQuantity($value),
                'actual_output_quantity' => $value === null ? null : $this->canonicalQuantity($value),
            ],
        );
    }

    private function canonicalQuantity(mixed $value): string
    {
        return bcadd((string) $value, '0', self::QUANTITY_SCALE);
    }
}
