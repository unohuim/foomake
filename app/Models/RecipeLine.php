<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Class RecipeLine
 *
 * Represents a single input line for a recipe.
 */
class RecipeLine extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'recipe_id',
        'item_id',
        'quantity',
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
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Booted model events.
     */
    protected static function booted(): void
    {
        static::saving(function (RecipeLine $line) {
            $recipe = $line->recipe;
            $item = $line->item;

            if ($recipe && $recipe->tenant_id !== $line->tenant_id) {
                throw new InvalidArgumentException('Recipe line tenant must match recipe tenant.');
            }

            if ($item && $item->tenant_id !== $line->tenant_id) {
                throw new InvalidArgumentException('Recipe line tenant must match item tenant.');
            }
        });
    }
}
