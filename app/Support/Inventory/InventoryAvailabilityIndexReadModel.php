<?php

namespace App\Support\Inventory;

use App\Models\Item;
use App\Models\MakeOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockMove;
use App\Support\QuantityFormatter;
use Illuminate\Support\Collection;

/**
 * Build inventory availability rows for the inventory index.
 */
class InventoryAvailabilityIndexReadModel
{
    private const SCALE = 6;

    public function __construct(
        private readonly InventoryBuyUomConversionResolver $buyUomConversionResolver
    ) {
    }

    /**
     * Build all tenant-scoped rows for the inventory index.
     */
    public function rows(int $tenantId, string $search = '', string $sortColumn = 'item', string $direction = 'asc'): Collection
    {
        $items = Item::query()
            ->where('tenant_id', $tenantId)
            ->with('baseUom')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->orderBy('name', $direction)
            ->get();

        if ($items->isEmpty()) {
            return collect();
        }

        $itemIds = $items->pluck('id')->all();
        $onHandByItemId = $this->onHandQuantities($tenantId, $itemIds);
        $sellByItemId = $this->sellQuantities($tenantId, $itemIds);
        $buyByItemId = $this->buyQuantities($tenantId, $itemIds);
        $makeByItemId = $this->makeQuantities($tenantId, $itemIds);

        return $items->map(function (Item $item) use (
            $onHandByItemId,
            $sellByItemId,
            $buyByItemId,
            $makeByItemId
        ): array {
            $onHand = $onHandByItemId[$item->id] ?? $this->zero();
            $sell = $sellByItemId[$item->id] ?? $this->zero();
            $buy = $buyByItemId[$item->id] ?? $this->zero();
            $make = $makeByItemId[$item->id] ?? $this->zero();
            $net = $this->netQuantity($onHand, $sell, $buy, $make);

            return [
                'id' => $item->id,
                'item' => $item->name,
                'item_uom_name' => $item->baseUom?->name,
                'show_url' => route('materials.show', $item),
                'on_hand' => $onHand,
                'on_hand_display' => QuantityFormatter::formatForUom($onHand, $item->baseUom, self::SCALE),
                'sell' => $sell,
                'sell_display' => QuantityFormatter::formatForUom($sell, $item->baseUom, self::SCALE),
                'buy' => $buy,
                'buy_display' => QuantityFormatter::formatForUom($buy, $item->baseUom, self::SCALE),
                'make' => $make,
                'make_display' => QuantityFormatter::formatForUom($make, $item->baseUom, self::SCALE),
                'net' => $net,
                'net_display' => QuantityFormatter::formatForUom($net, $item->baseUom, self::SCALE),
            ];
        });
    }

    /**
     * Build one tenant-scoped row for a single item.
     *
     * @return array<string, mixed>
     */
    public function rowForItem(Item $item): array
    {
        return $this->rows((int) $item->tenant_id)
            ->firstWhere('id', $item->id) ?? [
            'id' => $item->id,
            'item' => $item->name,
            'item_uom_name' => $item->baseUom?->name,
            'show_url' => route('materials.show', $item),
            'on_hand' => $this->zero(),
            'on_hand_display' => QuantityFormatter::formatForUom($this->zero(), $item->baseUom, self::SCALE),
            'sell' => $this->zero(),
            'sell_display' => QuantityFormatter::formatForUom($this->zero(), $item->baseUom, self::SCALE),
            'buy' => $this->zero(),
            'buy_display' => QuantityFormatter::formatForUom($this->zero(), $item->baseUom, self::SCALE),
            'make' => $this->zero(),
            'make_display' => QuantityFormatter::formatForUom($this->zero(), $item->baseUom, self::SCALE),
            'net' => $this->zero(),
            'net_display' => QuantityFormatter::formatForUom($this->zero(), $item->baseUom, self::SCALE),
        ];
    }

    /**
     * Aggregate posted stock-move balances per item.
     *
     * @param array<int, int> $itemIds
     * @return array<int, string>
     */
    private function onHandQuantities(int $tenantId, array $itemIds): array
    {
        $quantities = [];

        $moves = StockMove::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('item_id', $itemIds)
            ->where(function ($query): void {
                $query->where('status', 'POSTED')
                    ->orWhereNull('status');
            })
            ->get(['item_id', 'quantity']);

        foreach ($moves as $move) {
            $itemId = (int) $move->item_id;
            $quantities[$itemId] = isset($quantities[$itemId])
                ? bcadd($quantities[$itemId], (string) $move->quantity, self::SCALE)
                : bcadd((string) $move->quantity, '0', self::SCALE);
        }

        return $quantities;
    }

    /**
     * Aggregate direct and fulfillment-recipe sales demand per item.
     *
     * @param array<int, int> $itemIds
     * @return array<int, string>
     */
    private function sellQuantities(int $tenantId, array $itemIds): array
    {
        $quantities = [];

        $lines = SalesOrderLine::query()
            ->select('sales_order_lines.*')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->where('sales_order_lines.tenant_id', $tenantId)
            ->where('sales_orders.tenant_id', $tenantId)
            ->whereNotIn('sales_orders.status', [
                SalesOrder::STATUS_DRAFT,
                SalesOrder::STATUS_COMPLETED,
                SalesOrder::STATUS_CANCELLED,
            ])
            ->orderBy('sales_order_lines.id')
            ->get();

        if ($lines->isEmpty()) {
            return $quantities;
        }

        foreach ($lines as $line) {
            $itemId = (int) $line->item_id;

            if (in_array($itemId, $itemIds, true)) {
                $quantities[$itemId] = isset($quantities[$itemId])
                    ? bcadd($quantities[$itemId], (string) $line->quantity, self::SCALE)
                    : bcadd((string) $line->quantity, '0', self::SCALE);
            }
        }

        $selectedRecipesByOutputItemId = $this->selectedFulfillmentRecipesByOutputItemId(
            $tenantId,
            $lines->pluck('item_id')->unique()->map(fn ($id): int => (int) $id)->all()
        );

        if ($selectedRecipesByOutputItemId === []) {
            return $quantities;
        }

        $recipeLinesByRecipeId = $this->recipeLinesByRecipeId(array_values($selectedRecipesByOutputItemId));

        foreach ($lines as $line) {
            $recipe = $selectedRecipesByOutputItemId[(int) $line->item_id] ?? null;

            if (! $recipe) {
                continue;
            }

            $recipeOutputQuantity = bcadd((string) $recipe->output_quantity, '0', self::SCALE);

            if (bccomp($recipeOutputQuantity, $this->zero(), self::SCALE) !== 1) {
                continue;
            }

            $runs = bcdiv((string) $line->quantity, $recipeOutputQuantity, self::SCALE);

            foreach ($recipeLinesByRecipeId[$recipe->id] ?? collect() as $recipeLine) {
                $componentItemId = (int) $recipeLine->item_id;

                if (! in_array($componentItemId, $itemIds, true)) {
                    continue;
                }

                $requiredQuantity = bcmul((string) $recipeLine->quantity, $runs, self::SCALE);

                $quantities[$componentItemId] = isset($quantities[$componentItemId])
                    ? bcadd($quantities[$componentItemId], $requiredQuantity, self::SCALE)
                    : bcadd($requiredQuantity, '0', self::SCALE);
            }
        }

        return $quantities;
    }

    /**
     * Aggregate inbound purchase-order quantities per item.
     *
     * @param array<int, int> $itemIds
     * @return array<int, string>
     */
    private function buyQuantities(int $tenantId, array $itemIds): array
    {
        $quantities = [];

        $lines = PurchaseOrderLine::query()
            ->select('purchase_order_lines.*')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->leftJoin(
                'workflow_stages as current_workflow_stages',
                'current_workflow_stages.id',
                '=',
                'purchase_orders.current_workflow_stage_id'
            )
            ->leftJoin(
                'workflow_domains as current_workflow_domains',
                'current_workflow_domains.id',
                '=',
                'current_workflow_stages.workflow_domain_id'
            )
            ->where('purchase_order_lines.tenant_id', $tenantId)
            ->where('purchase_orders.tenant_id', $tenantId)
            ->whereNull('purchase_orders.cancelled_at')
            ->whereNull('purchase_orders.workflow_cancelled_at')
            ->where('purchase_orders.status', '!=', PurchaseOrder::STATUS_CANCELLED)
            ->where(function ($query): void {
                $query
                    ->where(function ($legacyQuery): void {
                        $legacyQuery
                            ->whereNull('purchase_orders.current_workflow_stage_id')
                            ->whereNull('purchase_orders.last_completed_workflow_stage_id')
                            ->whereNotIn('purchase_orders.status', [
                                PurchaseOrder::STATUS_COMPLETED,
                                PurchaseOrder::STATUS_CANCELLED,
                            ]);
                    })
                    ->orWhere(function ($workflowQuery): void {
                        $workflowQuery
                            ->where('current_workflow_domains.key', 'purchasing')
                            ->where('current_workflow_stages.is_active', true)
                            ->whereNotNull('purchase_orders.current_workflow_stage_id');
                    });
            })
            ->with(['purchaseOption.item.baseUom', 'purchaseOption.packUom'])
            ->orderBy('purchase_order_lines.id')
            ->get();

        foreach ($lines as $line) {
            $itemId = (int) $line->item_id;

            if (! in_array($itemId, $itemIds, true)) {
                continue;
            }

            if ($line->purchaseOption === null) {
                continue;
            }

            $inboundQuantity = $this->buyUomConversionResolver->baseQuantityFor(
                $line->purchaseOption,
                bcadd((string) $line->pack_count, '0', self::SCALE)
            );

            if ($inboundQuantity === null) {
                continue;
            }

            $quantities[$itemId] = isset($quantities[$itemId])
                ? bcadd($quantities[$itemId], $inboundQuantity, self::SCALE)
                : bcadd($inboundQuantity, '0', self::SCALE);
        }

        return $quantities;
    }

    /**
     * Aggregate non-terminal make-order output quantities per item.
     *
     * @param array<int, int> $itemIds
     * @return array<int, string>
     */
    private function makeQuantities(int $tenantId, array $itemIds): array
    {
        $quantities = [];

        $makeOrders = MakeOrder::query()
            ->select([
                'make_orders.output_item_id',
                'make_orders.expected_output_qty',
            ])
            ->where('make_orders.tenant_id', $tenantId)
            ->whereIn('make_orders.output_item_id', $itemIds)
            ->whereNotIn('make_orders.status', [
                MakeOrder::STATUS_DRAFT,
                MakeOrder::STATUS_MADE,
                MakeOrder::STATUS_CANCELLED,
            ])
            ->get();

        foreach ($makeOrders as $makeOrder) {
            $itemId = (int) $makeOrder->output_item_id;
            $producedQuantity = bcadd((string) ($makeOrder->expected_output_qty ?? '0.000000'), '0', self::SCALE);

            $quantities[$itemId] = isset($quantities[$itemId])
                ? bcadd($quantities[$itemId], $producedQuantity, self::SCALE)
                : bcadd($producedQuantity, '0', self::SCALE);
        }

        return $quantities;
    }

    /**
     * Select the fulfillment recipe used for each output item.
     *
     * @param array<int, int> $outputItemIds
     * @return array<int, Recipe>
     */
    private function selectedFulfillmentRecipesByOutputItemId(int $tenantId, array $outputItemIds): array
    {
        if ($outputItemIds === []) {
            return [];
        }

        $recipes = Recipe::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('item_id', $outputItemIds)
            ->where('recipe_type', Recipe::TYPE_FULFILLMENT)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $selected = [];

        foreach ($recipes as $recipe) {
            $outputItemId = (int) $recipe->item_id;

            if (! isset($selected[$outputItemId])) {
                $selected[$outputItemId] = $recipe;
            }
        }

        return $selected;
    }

    /**
     * Load recipe lines grouped by recipe id.
     *
     * @param array<int, Recipe> $recipes
     * @return array<int, Collection<int, RecipeLine>>
     */
    private function recipeLinesByRecipeId(array $recipes): array
    {
        $recipeIds = collect($recipes)->map(fn (Recipe $recipe): int => (int) $recipe->id)->all();

        return RecipeLine::query()
            ->whereIn('recipe_id', $recipeIds)
            ->orderBy('id')
            ->get()
            ->groupBy('recipe_id')
            ->all();
    }

    /**
     * Compute the net quantity from the component quantities.
     */
    private function netQuantity(string $onHand, string $sell, string $buy, string $make): string
    {
        $net = bcsub($onHand, $sell, self::SCALE);
        $net = bcadd($net, $buy, self::SCALE);

        return bcadd($net, $make, self::SCALE);
    }

    /**
     * Return the canonical zero quantity.
     */
    private function zero(): string
    {
        return '0.000000';
    }
}
