<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Item;
use App\Models\MakeOrder;
use App\Models\MakeOrderLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Support\QuantityFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Build the stockable Material detail inventory stats strip read model.
 */
class BuildMaterialInventoryStatsAction
{
    private const SCALE = 6;

    /**
     * Build the inventory stats payload for one stockable material.
     *
     * @return array<string, mixed>
     */
    public function execute(Item $item): array
    {
        $onHand = $item->onHandQuantity();
        $openSales = $this->openSalesQuantity($item);
        $openPurchase = $this->openPurchaseQuantity($item);
        $openMakeOutput = $this->openMakeOutputQuantity($item);
        $openMakeIngredient = $this->openMakeIngredientQuantity($item);
        $openMakeNet = bcsub($openMakeOutput, $openMakeIngredient, self::SCALE);
        $netQuantity = $onHand;
        $netQuantity = bcsub($netQuantity, $openSales, self::SCALE);
        $netQuantity = bcadd($netQuantity, $openPurchase, self::SCALE);
        $netQuantity = bcadd($netQuantity, $openMakeOutput, self::SCALE);
        $netQuantity = bcsub($netQuantity, $openMakeIngredient, self::SCALE);

        $cards = collect([
            $this->card('on_hand', 'On Hand', $onHand, $item),
            $this->salesCard($item, $openSales),
            $this->purchaseCard($item, $openPurchase),
            $this->makeCard($item, $openMakeNet, $openMakeOutput, $openMakeIngredient),
            $this->card('net', 'Net Qty', $netQuantity, $item),
        ])->filter()->values()->all();

        return [
            'on_hand_quantity' => $onHand,
            'open_sales_quantity' => $openSales,
            'open_purchase_quantity' => $openPurchase,
            'open_make_output_quantity' => $openMakeOutput,
            'open_make_ingredient_quantity' => $openMakeIngredient,
            'open_make_quantity' => $openMakeNet,
            'net_quantity' => $netQuantity,
            'cards' => $cards,
        ];
    }

    private function openSalesQuantity(Item $item): string
    {
        $total = '0.000000';

        $quantities = SalesOrderLine::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('item_id', $item->id)
            ->whereHas('salesOrder', function ($query): void {
                $query->whereNotIn('status', [
                    SalesOrder::STATUS_COMPLETED,
                    SalesOrder::STATUS_CANCELLED,
                ]);
            })
            ->pluck('quantity');

        foreach ($quantities as $quantity) {
            $total = bcadd($total, (string) $quantity, self::SCALE);
        }

        return $total;
    }

    private function openPurchaseQuantity(Item $item): string
    {
        $total = '0.000000';

        /** @var Collection<int, PurchaseOrderLine> $lines */
        $lines = PurchaseOrderLine::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('item_id', $item->id)
            ->whereHas('purchaseOrder', function ($query): void {
                $query->whereNotIn('status', [
                    PurchaseOrder::STATUS_RECEIVED,
                    PurchaseOrder::STATUS_SHORT_CLOSED,
                    PurchaseOrder::STATUS_CANCELLED,
                ]);
            })
            ->with('purchaseOption')
            ->get();

        foreach ($lines as $line) {
            $packCount = bcadd((string) $line->pack_count, '0', self::SCALE);
            $received = $this->sumReceivedForPurchaseOrderLine((int) $line->id, (int) $item->tenant_id);
            $shortClosed = $this->sumShortClosedForPurchaseOrderLine((int) $line->id, (int) $item->tenant_id);
            $remaining = bcsub($packCount, $received, self::SCALE);
            $remaining = bcsub($remaining, $shortClosed, self::SCALE);

            if (bccomp($remaining, '0.000000', self::SCALE) !== 1) {
                continue;
            }

            $packQuantity = $line->purchaseOption?->pack_quantity;

            if ($packQuantity === null) {
                continue;
            }

            $total = bcadd(
                $total,
                bcmul($remaining, bcadd((string) $packQuantity, '0', self::SCALE), self::SCALE),
                self::SCALE
            );
        }

        return $total;
    }

    private function openMakeOutputQuantity(Item $item): string
    {
        $total = '0.000000';

        /** @var Collection<int, MakeOrder> $makeOrders */
        $makeOrders = MakeOrder::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('output_item_id', $item->id)
            ->whereNotIn('status', [
                MakeOrder::STATUS_MADE,
                MakeOrder::STATUS_CANCELLED,
            ])
            ->with('recipeVersion')
            ->get();

        foreach ($makeOrders as $makeOrder) {
            $outputQuantity = bcadd((string) ($makeOrder->expected_output_qty ?? '0.000000'), '0', self::SCALE);
            $total = bcadd($total, $outputQuantity, self::SCALE);
        }

        return $total;
    }

    private function openMakeIngredientQuantity(Item $item): string
    {
        $total = '0.000000';

        $quantities = MakeOrderLine::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('input_item_id', $item->id)
            ->whereHas('makeOrder', function ($query): void {
                $query->whereNotIn('status', [
                    MakeOrder::STATUS_MADE,
                    MakeOrder::STATUS_CANCELLED,
                ]);
            })
            ->pluck('planned_quantity');

        foreach ($quantities as $quantity) {
            $total = bcadd($total, (string) $quantity, self::SCALE);
        }

        return $total;
    }

    /**
     * @return array<string, string>|null
     */
    private function salesCard(Item $item, string $quantity): ?array
    {
        if (! $item->is_sellable) {
            return null;
        }

        return $this->card('open_sales', 'Open Sales Orders Qty', $quantity, $item);
    }

    /**
     * @return array<string, string>|null
     */
    private function purchaseCard(Item $item, string $quantity): ?array
    {
        if (! $item->is_purchasable) {
            return null;
        }

        return $this->card('open_purchase', 'Open Purchase Orders Qty', $quantity, $item);
    }

    /**
     * @return array<string, string>|null
     */
    private function makeCard(
        Item $item,
        string $netQuantity,
        string $outputQuantity,
        string $ingredientQuantity
    ): ?array {
        if (
            ! $item->is_manufacturable
            && bccomp($outputQuantity, '0.000000', self::SCALE) === 0
            && bccomp($ingredientQuantity, '0.000000', self::SCALE) === 0
        ) {
            return null;
        }

        return $this->card('open_make', 'Open Make Orders Impact', $netQuantity, $item);
    }

    /**
     * @return array<string, string>
     */
    private function card(string $key, string $label, string $quantity, Item $item): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'quantity' => $quantity,
            'quantity_display' => QuantityFormatter::formatForUom($quantity, $item->baseUom, 2),
            'uom_symbol' => (string) ($item->baseUom?->symbol ?? ''),
        ];
    }

    private function sumReceivedForPurchaseOrderLine(int $lineId, int $tenantId): string
    {
        $total = '0.000000';

        $rows = DB::table('purchase_order_receipt_lines')
            ->where('tenant_id', $tenantId)
            ->where('purchase_order_line_id', $lineId)
            ->get(['received_quantity']);

        foreach ($rows as $row) {
            $total = bcadd($total, (string) $row->received_quantity, self::SCALE);
        }

        return $total;
    }

    private function sumShortClosedForPurchaseOrderLine(int $lineId, int $tenantId): string
    {
        $total = '0.000000';

        $rows = DB::table('purchase_order_short_closure_lines')
            ->where('tenant_id', $tenantId)
            ->where('purchase_order_line_id', $lineId)
            ->get(['short_closed_quantity']);

        foreach ($rows as $row) {
            $total = bcadd($total, (string) $row->short_closed_quantity, self::SCALE);
        }

        return $total;
    }
}
