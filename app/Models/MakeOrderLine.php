<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshotted execution line for a make order.
 */
class MakeOrderLine extends Model
{
    use HasTenantScope;

    public const TYPE_RECIPE = 'recipe';
    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';
    public const TYPE_SUBSTITUTION = 'substitution';

    protected $fillable = [
        'tenant_id',
        'make_order_id',
        'source_recipe_version_line_id',
        'input_item_id',
        'uom_id',
        'planned_quantity',
        'actual_quantity',
        'line_type',
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:6',
        'actual_quantity' => 'decimal:6',
    ];

    /**
     * Owning tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Owning make order.
     */
    public function makeOrder(): BelongsTo
    {
        return $this->belongsTo(MakeOrder::class);
    }

    /**
     * Source recipe version line when the snapshot came from a recipe.
     */
    public function sourceRecipeVersionLine(): BelongsTo
    {
        return $this->belongsTo(RecipeVersionLine::class, 'source_recipe_version_line_id');
    }

    /**
     * Input item for the snapshot line.
     */
    public function inputItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'input_item_id');
    }

    /**
     * UoM used by the snapshot line.
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}
