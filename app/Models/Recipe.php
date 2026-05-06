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
 *
 * @property string $recipe_type
 */
class Recipe extends Model
{
    use HasTenantScope;

    public const TYPE_MANUFACTURING = 'manufacturing';

    public const TYPE_FULFILLMENT = 'fulfillment';

    public const FULFILLMENT_OUTPUT_QUANTITY = '1.000000';

    protected $fillable = [
        'tenant_id',
        'item_id',
        'recipe_type',
        'name',
        'output_quantity',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'output_quantity' => 'decimal:6',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected $attributes = [
        'recipe_type' => self::TYPE_MANUFACTURING,
    ];

    /**
     * @return array<int, string>
     */
    public static function allowedRecipeTypes(): array
    {
        return [
            self::TYPE_MANUFACTURING,
            self::TYPE_FULFILLMENT,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function recipeTypeLabels(): array
    {
        return [
            self::TYPE_MANUFACTURING => 'Manufacturing',
            self::TYPE_FULFILLMENT => 'Fulfillment',
        ];
    }

    /**
     * Determine whether the given recipe type is valid.
     */
    public static function isValidRecipeType(?string $recipeType): bool
    {
        return in_array($recipeType, self::allowedRecipeTypes(), true);
    }

    /**
     * Resolve the display label for a recipe type.
     */
    public static function labelForRecipeType(?string $recipeType): string
    {
        return self::recipeTypeLabels()[$recipeType] ?? '—';
    }

    /**
     * Resolve this recipe's type label.
     */
    public function recipeTypeLabel(): string
    {
        return self::labelForRecipeType($this->recipe_type);
    }

    /**
     * Determine whether this recipe is manufacturing-scoped.
     */
    public function isManufacturingType(): bool
    {
        return $this->recipe_type === self::TYPE_MANUFACTURING;
    }

    /**
     * Resolve an eligibility error for a recipe type and output item pairing.
     */
    public static function recipeTypeEligibilityError(Item $item, ?string $recipeType): ?string
    {
        $isManufacturable = (bool) $item->is_manufacturable;
        $isSellable = (bool) $item->is_sellable;

        if (! self::isValidRecipeType($recipeType)) {
            return 'Recipe type is invalid.';
        }

        if ($recipeType === self::TYPE_MANUFACTURING && ! $isManufacturable) {
            return 'Manufacturing recipes require a manufacturable output item.';
        }

        if ($recipeType === self::TYPE_FULFILLMENT && ! $isSellable) {
            return 'Fulfillment recipes require a sellable output item.';
        }

        return null;
    }

    /**
     * Normalize output quantity for the selected recipe type.
     */
    public static function normalizeOutputQuantityForType(?string $recipeType, ?string $outputQuantity): string
    {
        if ($recipeType === self::TYPE_FULFILLMENT) {
            return self::FULFILLMENT_OUTPUT_QUANTITY;
        }

        return (string) ($outputQuantity ?? '0.000000');
    }

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
            if (
                ! array_key_exists('recipe_type', $recipe->getAttributes())
                || $recipe->getAttribute('recipe_type') === null
            ) {
                $recipe->recipe_type = self::TYPE_MANUFACTURING;
            }

            $recipe->output_quantity = self::normalizeOutputQuantityForType(
                $recipe->recipe_type,
                $recipe->output_quantity
            );

            $item = $recipe->item;

            if (! self::isValidRecipeType($recipe->recipe_type)) {
                throw new InvalidArgumentException('Recipe type is invalid.');
            }

            if (!$item) {
                throw new InvalidArgumentException('Recipe requires a valid output item.');
            }

            if ($item->tenant_id !== $recipe->tenant_id) {
                throw new InvalidArgumentException('Recipe tenant must match item tenant.');
            }

            $eligibilityError = self::recipeTypeEligibilityError($item, $recipe->recipe_type);

            if ($eligibilityError !== null) {
                throw new InvalidArgumentException($eligibilityError);
            }
        });
    }
}
