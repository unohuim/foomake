<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;

/**
 * Represents the stable parent identity for a versioned recipe.
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
        'current_version_id',
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
     * Owning tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Output item produced by the recipe.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Current published execution template.
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class, 'current_version_id');
    }

    /**
     * All version records for the recipe.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(RecipeVersion::class);
    }

    /**
     * Legacy mirrored lines on the parent recipe.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(RecipeLine::class);
    }

    /**
     * User checkout records for this recipe.
     */
    public function versionCheckouts(): HasMany
    {
        return $this->hasMany(RecipeVersionCheckout::class);
    }

    /**
     * Stock moves created from executing the recipe.
     */
    public function stockMoves(): MorphMany
    {
        return $this->morphMany(StockMove::class, 'source');
    }

    /**
     * Resolve the currently published version when one exists.
     */
    public function currentPublishedVersion(): ?RecipeVersion
    {
        $currentVersion = $this->currentVersion;

        if (! $currentVersion || ! $currentVersion->isPublished()) {
            return null;
        }

        return $currentVersion;
    }

    /**
     * Resolve the most recent open checkout for a user on this recipe.
     */
    public function openCheckoutForUser(int $userId): ?RecipeVersionCheckout
    {
        return $this->versionCheckouts()
            ->where('user_id', $userId)
            ->whereNull('checked_in_at')
            ->latest('checked_out_at')
            ->latest('id')
            ->first();
    }

    /**
     * Resolve the version shown to a specific user.
     */
    public function displayVersionForUser(int $userId): ?RecipeVersion
    {
        $checkout = $this->openCheckoutForUser($userId);

        if ($checkout?->recipeVersion) {
            return $checkout->recipeVersion;
        }

        $currentPublishedVersion = $this->currentPublishedVersion();

        if ($currentPublishedVersion) {
            return $currentPublishedVersion;
        }

        return $this->versions()
            ->orderByRaw(
                'CASE WHEN status = ? THEN 1 ELSE 0 END',
                [RecipeVersion::STATUS_ARCHIVED]
            )
            ->orderByDesc('version_number')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Resolve the current published output quantity.
     */
    public function currentOutputQuantity(): string
    {
        return (string) ($this->currentPublishedVersion()?->output_quantity ?? $this->output_quantity ?? '0.000000');
    }

    /**
     * Resolve the current version lifecycle status.
     */
    public function currentVersionStatus(): ?string
    {
        return RecipeVersion::normalizeStatus($this->currentVersion?->status);
    }

    /**
     * Determine the next stored version number, with legacy-safe upgrading.
     */
    public function nextVersionNumber(): int
    {
        $maxVersionNumber = (int) ($this->versions()->max('version_number') ?? 0);

        if ($maxVersionNumber === 0) {
            return 100;
        }

        if ($maxVersionNumber < 100) {
            return ($maxVersionNumber * 100) + 1;
        }

        return $maxVersionNumber + 1;
    }

    /**
     * Booted model events.
     */
    protected static function booted(): void
    {
        static::saving(function (Recipe $recipe): void {
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

            if (! $item) {
                throw new InvalidArgumentException('Recipe requires a valid output item.');
            }

            if ((int) $item->tenant_id !== (int) $recipe->tenant_id) {
                throw new InvalidArgumentException('Recipe tenant must match item tenant.');
            }

            $eligibilityError = self::recipeTypeEligibilityError($item, $recipe->recipe_type);

            if ($eligibilityError !== null) {
                throw new InvalidArgumentException($eligibilityError);
            }
        });
    }
}
