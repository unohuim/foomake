<?php

namespace App\Actions\Inventory;

use App\Models\Recipe;
use App\Models\StockMove;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Execute a recipe by posting inventory stock moves.
 */
class ExecuteRecipeAction
{
    /**
     * Execute a recipe for a given output quantity.
     *
     * @param Recipe $recipe
     * @param string $outputQuantity
     * @return array<int, StockMove>
     */
    public function execute(Recipe $recipe, string $outputQuantity): array
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

        $outputQuantityDecimal = BigDecimal::of($outputQuantity);

        if ($outputQuantityDecimal->isLessThanOrEqualTo('0')) {
            throw new InvalidArgumentException('Output quantity must be greater than zero.');
        }

        $lines = $recipe->lines()->with('item')->get();

        return DB::transaction(function () use ($recipe, $outputItem, $outputQuantityDecimal, $lines) {
            $stockMoves = [];

            foreach ($lines as $line) {
                $inputItem = $line->item;

                if (!$inputItem) {
                    throw new InvalidArgumentException('Recipe line input item is missing.');
                }

                $inputQuantity = BigDecimal::of($line->quantity)
                    ->multipliedBy($outputQuantityDecimal)
                    ->toScale(6, RoundingMode::HALF_UP);

                $stockMoves[] = StockMove::create([
                    'tenant_id' => $recipe->tenant_id,
                    'item_id' => $inputItem->id,
                    'uom_id' => $inputItem->base_uom_id,
                    'quantity' => (string) $inputQuantity->negated(),
                    'type' => 'issue',
                    'source_id' => $recipe->id,
                    'source_type' => Recipe::class,
                ]);
            }

            $outputQuantityScaled = $outputQuantityDecimal->toScale(6, RoundingMode::HALF_UP);

            $stockMoves[] = StockMove::create([
                'tenant_id' => $recipe->tenant_id,
                'item_id' => $outputItem->id,
                'uom_id' => $outputItem->base_uom_id,
                'quantity' => (string) $outputQuantityScaled,
                'type' => 'receipt',
                'source_id' => $recipe->id,
                'source_type' => Recipe::class,
            ]);

            return $stockMoves;
        });
    }
}
