<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versioned recipe input line.
 */
class RecipeVersionLine extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'recipe_version_id',
        'input_item_id',
        'uom_id',
        'quantity',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
    ];

    /**
     * Owning tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Owning version.
     */
    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class);
    }

    /**
     * Input item consumed by the version.
     */
    public function inputItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'input_item_id');
    }

    /**
     * Base or explicit UoM for the planned quantity.
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}
