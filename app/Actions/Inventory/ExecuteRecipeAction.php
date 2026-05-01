<?php

namespace App\Actions\Inventory;

use App\Models\Recipe;
use App\Models\StockMove;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Execute a recipe by posting inventory stock moves.
 *
 */
class ExecuteRecipeAction
{
    /**
     * Execute a recipe for a given number of runs.
     *
     * @param Recipe $recipe
     * @param string $runs
     * @return array<int, StockMove>
     */
    public function execute(Recipe $recipe, string $runs): array
    {
        if (auth()->check()) {
            $userTenantId = auth()->user()->tenant_id;

            if ($userTenantId !== $recipe->tenant_id) {
                throw new InvalidArgumentException('Recipe tenant does not match authenticated user tenant.');
            }
        }

        if (!$recipe->is_active) {
            throw new InvalidArgumentException('Recipe must be active to execute.');
        }

        $outputItem = $recipe->item;

        if (!$outputItem || !$outputItem->is_manufacturable) {
            throw new InvalidArgumentException('Recipe output item must be manufacturable.');
        }

        if (!preg_match('/^\d+(?:\.\d{1,6})?$/', $runs)) {
            throw new InvalidArgumentException('Runs must be a valid decimal with up to 6 decimal places.');
        }

        if (bccomp($runs, '0.000000', 6) !== 1) {
            throw new InvalidArgumentException('Runs must be greater than zero.');
        }

        $recipeOutputQuantity = (string) $recipe->output_quantity;

        if (!preg_match('/^\d+(?:\.\d{1,6})?$/', $recipeOutputQuantity)) {
            throw new InvalidArgumentException('Recipe output quantity must be a valid decimal with up to 6 decimal places.');
        }

        if (bccomp($recipeOutputQuantity, '0.000000', 6) !== 1) {
            throw new InvalidArgumentException('Recipe output quantity must be greater than zero.');
        }

        $lines = $recipe->lines()->with('item')->get();

        return DB::transaction(function () use ($recipe, $outputItem, $recipeOutputQuantity, $runs, $lines) {
            $stockMoves = [];

            foreach ($lines as $line) {
                $inputItem = $line->item;

                if (!$inputItem) {
                    throw new InvalidArgumentException('Recipe line input item is missing.');
                }

                $inputQuantity = bcmul((string) $line->quantity, $runs, 6);

                $stockMoves[] = StockMove::create([
                    'tenant_id' => $recipe->tenant_id,
                    'item_id' => $inputItem->id,
                    'uom_id' => $inputItem->base_uom_id,
                    'quantity' => bcsub('0.000000', $inputQuantity, 6),
                    'type' => 'issue',
                    'source_id' => $recipe->id,
                    'source_type' => Recipe::class,
                ]);
            }

            $outputQuantityScaled = bcmul($recipeOutputQuantity, $runs, 6);

            $stockMoves[] = StockMove::create([
                'tenant_id' => $recipe->tenant_id,
                'item_id' => $outputItem->id,
                'uom_id' => $outputItem->base_uom_id,
                'quantity' => $outputQuantityScaled,
                'type' => 'receipt',
                'source_id' => $recipe->id,
                'source_type' => Recipe::class,
            ]);

            return $stockMoves;
        });
    }
}
