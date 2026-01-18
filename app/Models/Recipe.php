<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;

/**
 * Class Recipe
 *
 * Represents a bill of materials (BOM) for a manufacturable item.
 */
class Recipe extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'item_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Output item produced by the recipe.
     *
     * @return BelongsTo
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Input lines consumed by the recipe.
     *
     * @return HasMany
     */
    public function lines(): HasMany
    {
        return $this->hasMany(RecipeLine::class);
    }

    /**
     * Stock moves created from executing the recipe.
     *
     * @return MorphMany
     */
    public function stockMoves(): MorphMany
    {
        return $this->morphMany(StockMove::class, 'source');
    }

    /**
     * Booted model events.
     */
    protected static function booted(): void
    {
        static::saving(function (Recipe $recipe) {
            $item = $recipe->item;

            if (!$item) {
                throw new InvalidArgumentException('Recipe requires a valid output item.');
            }

            if ($item->tenant_id !== $recipe->tenant_id) {
                throw new InvalidArgumentException('Recipe tenant must match item tenant.');
            }

            if (!$item->is_manufacturable) {
                throw new InvalidArgumentException('Recipe output item must be manufacturable.');
            }

            if ($recipe->is_active) {
                $existing = static::withoutGlobalScopes()
                    ->where('tenant_id', $recipe->tenant_id)
                    ->where('item_id', $recipe->item_id)
                    ->where('is_active', true);

                if ($recipe->exists) {
                    $existing->where('id', '!=', $recipe->id);
                }

                if ($existing->exists()) {
                    throw new InvalidArgumentException('Only one active recipe is allowed per item.');
                }
            }
        });
    }
}
