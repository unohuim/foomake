<?php

namespace App\Actions\Sales;

use App\Models\Item;
use App\Models\Recipe;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use DomainException;

/**
 * Build the inventory issue plan for packing a sales order.
 */
class BuildSalesOrderIssuePlanAction
{
    private const SCALE = 6;

    /**
     * Build issue move payloads and validate availability.
     *
     * @return array<int, array<string, int|string>>
     */
    public function execute(SalesOrder $salesOrder): array
    {
        $lines = SalesOrderLine::query()
            ->where('sales_order_id', $salesOrder->id)
            ->with('item')
            ->orderBy('id')
            ->get();

        $plan = [];
        $requiredByItemId = [];
        $itemsById = [];

        foreach ($lines as $line) {
            if ((int) $line->tenant_id !== (int) $salesOrder->tenant_id) {
                throw new DomainException('Sales order line tenant mismatch.');
            }

            $item = $line->item;

            if (! $item || (int) $item->tenant_id !== (int) $salesOrder->tenant_id) {
                throw new DomainException('Sales order line item is invalid.');
            }

            $fulfillmentRecipe = $this->fulfillmentRecipeForItem($salesOrder, $item);

            if ($fulfillmentRecipe !== null) {
                $this->appendFulfillmentRecipeMoves($plan, $requiredByItemId, $itemsById, $line, $fulfillmentRecipe);
                continue;
            }

            $this->appendIssueMove($plan, $requiredByItemId, $itemsById, $line, $item, (string) $line->quantity);
        }

        $this->assertInventoryAvailability($requiredByItemId, $itemsById);

        return $plan;
    }

    /**
     * Resolve the active fulfillment recipe for a sold item, if any.
     */
    private function fulfillmentRecipeForItem(SalesOrder $salesOrder, Item $item): ?Recipe
    {
        return Recipe::query()
            ->where('tenant_id', $salesOrder->tenant_id)
            ->where('item_id', $item->id)
            ->where('recipe_type', Recipe::TYPE_FULFILLMENT)
            ->where('is_active', true)
            ->with(['lines.item'])
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /**
     * Append component issue moves for a fulfillment recipe line.
     *
     * @param array<int, array<string, int|string>> $plan
     * @param array<int, string> $requiredByItemId
     * @param array<int, Item> $itemsById
     */
    private function appendFulfillmentRecipeMoves(
        array &$plan,
        array &$requiredByItemId,
        array &$itemsById,
        SalesOrderLine $line,
        Recipe $recipe
    ): void {
        if ($recipe->lines->isEmpty()) {
            throw new DomainException('Fulfillment recipe must have at least one line.');
        }

        $recipeOutputQuantity = (string) $recipe->output_quantity;

        if (bccomp($recipeOutputQuantity, '0.000000', self::SCALE) !== 1) {
            throw new DomainException('Fulfillment recipe output quantity must be greater than zero.');
        }

        $runs = bcdiv((string) $line->quantity, $recipeOutputQuantity, self::SCALE);

        foreach ($recipe->lines as $recipeLine) {
            $component = $recipeLine->item;

            if (! $component || (int) $component->tenant_id !== (int) $line->tenant_id) {
                throw new DomainException('Fulfillment recipe component item is invalid.');
            }

            $requiredQuantity = bcmul((string) $recipeLine->quantity, $runs, self::SCALE);

            $this->appendIssueMove($plan, $requiredByItemId, $itemsById, $line, $component, $requiredQuantity);
        }
    }

    /**
     * Append one planned issue move and aggregate required quantities by item.
     *
     * @param array<int, array<string, int|string>> $plan
     * @param array<int, string> $requiredByItemId
     * @param array<int, Item> $itemsById
     */
    private function appendIssueMove(
        array &$plan,
        array &$requiredByItemId,
        array &$itemsById,
        SalesOrderLine $line,
        Item $item,
        string $requiredQuantity
    ): void {
        $normalizedQuantity = bcadd($requiredQuantity, '0', self::SCALE);

        $plan[] = [
            'tenant_id' => $line->tenant_id,
            'item_id' => $item->id,
            'uom_id' => $item->base_uom_id,
            'quantity' => bcsub('0.000000', $normalizedQuantity, self::SCALE),
            'type' => 'issue',
            'status' => 'POSTED',
            'source_type' => SalesOrderLine::class,
            'source_id' => $line->id,
        ];

        $requiredByItemId[$item->id] = isset($requiredByItemId[$item->id])
            ? bcadd($requiredByItemId[$item->id], $normalizedQuantity, self::SCALE)
            : $normalizedQuantity;

        $itemsById[$item->id] = $item;
    }

    /**
     * Ensure on-hand inventory covers the aggregated required quantities.
     *
     * @param array<int, string> $requiredByItemId
     * @param array<int, Item> $itemsById
     */
    private function assertInventoryAvailability(array $requiredByItemId, array $itemsById): void
    {
        foreach ($requiredByItemId as $itemId => $requiredQuantity) {
            $item = $itemsById[$itemId] ?? null;

            if (! $item) {
                throw new DomainException('Inventory item is invalid.');
            }

            if (bccomp($item->onHandQuantity(), $requiredQuantity, self::SCALE) === -1) {
                throw new DomainException('Insufficient inventory for ' . $item->name . '.');
            }
        }
    }
}

