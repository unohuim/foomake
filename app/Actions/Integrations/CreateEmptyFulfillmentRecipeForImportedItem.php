<?php

namespace App\Actions\Integrations;

use App\Models\Item;
use App\Models\Recipe;

/**
 * Create the default empty fulfillment recipe for a newly imported ecommerce item.
 */
class CreateEmptyFulfillmentRecipeForImportedItem
{
    /**
     * Create the empty fulfillment recipe when it does not already exist.
     */
    public function execute(Item $item): bool
    {
        $existingRecipe = Recipe::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('item_id', $item->id)
            ->where('recipe_type', Recipe::TYPE_FULFILLMENT)
            ->exists();

        if ($existingRecipe) {
            return false;
        }

        Recipe::query()->create([
            'tenant_id' => $item->tenant_id,
            'item_id' => $item->id,
            'recipe_type' => Recipe::TYPE_FULFILLMENT,
            'name' => $item->name,
            'output_quantity' => Recipe::FULFILLMENT_OUTPUT_QUANTITY,
            'is_active' => true,
            'is_default' => true,
        ]);

        return true;
    }
}
