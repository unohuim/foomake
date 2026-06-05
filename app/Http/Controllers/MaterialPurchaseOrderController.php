<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Uom;
use App\Support\QuantityFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class MaterialPurchaseOrderController extends Controller
{
    /**
     * Display a paginated list of purchase orders that include the current material.
     */
    public function index(Request $request, Item $item): JsonResponse
    {
        Gate::authorize('inventory-materials-view');
        Gate::authorize('purchasing-purchase-orders-create');

        $perPage = $this->perPageFromRequest($request);
        $paginator = PurchaseOrder::query()
            ->with([
                'supplier',
                'lines' => function ($query) use ($item): void {
                    $query
                        ->where('item_id', $item->id)
                        ->with('purchaseOption.packUom');
                },
            ])
            ->whereHas('lines', function ($query) use ($item): void {
                $query->where('item_id', $item->id);
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = $paginator->getCollection()
            ->map(fn (PurchaseOrder $purchaseOrder): array => $this->rowPayload($purchaseOrder))
            ->values()
            ->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Resolve the page size for reusable detail-section list endpoints.
     */
    private function perPageFromRequest(Request $request): int
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return (int) ($validated['per_page'] ?? 5);
    }

    /**
     * @return array<string, mixed>
     */
    private function rowPayload(PurchaseOrder $purchaseOrder): array
    {
        $lines = $purchaseOrder->lines;
        $lineTotalCents = (int) $lines->sum(
            fn (PurchaseOrderLine $line): int => (int) $line->line_subtotal_cents
        );
        $purchaseQuantity = $this->totalPurchaseUomQuantity($lines);
        $totalQuantity = $purchaseQuantity['quantity'];
        $purchaseUom = $purchaseQuantity['uom'];
        $currencyCode = strtoupper(
            (string) ($lines->first()?->unit_price_currency_code ?: 'USD')
        );
        $unitCostAmount = $this->unitCostAmount($lineTotalCents, $totalQuantity);

        return [
            'id' => $purchaseOrder->id,
            'order_date' => $purchaseOrder->order_date?->format('F j, Y'),
            'supplier_name' => $purchaseOrder->supplier?->company_name,
            'po_number' => $purchaseOrder->po_number ?? null,
            'po_grand_total_cents' => $purchaseOrder->po_grand_total_cents,
            'material_quantity_display' => $totalQuantity === null
                ? null
                : $this->formatGroupedQuantity(
                    QuantityFormatter::formatForUom($totalQuantity, $purchaseUom, 2)
                ),
            'material_uom_symbol' => $purchaseQuantity['mixed']
                ? 'Multiple UoMs'
                : $purchaseUom?->symbol,
            'material_line_total_amount_display' => $this->formatMoneyCents($lineTotalCents),
            'material_line_total_currency_code' => $currencyCode,
            'material_unit_cost_amount_display' => $unitCostAmount === null
                ? null
                : $this->formatMoneyAmount($unitCostAmount),
            'material_unit_cost_currency_code' => $currencyCode,
            'status' => $purchaseOrder->status,
            'is_cancelled' => $purchaseOrder->isCancelled(),
            'is_back_ordered' => $purchaseOrder->back_ordered_at !== null,
            'show_url' => route('purchasing.orders.show', $purchaseOrder),
            'available_actions' => ['view'],
        ];
    }

    /**
     * Sum purchase-order package quantities in their supplier package UoM.
     *
     * @param Collection<int, PurchaseOrderLine> $lines
     * @return array{quantity: ?string, uom: ?Uom, mixed: bool}
     */
    private function totalPurchaseUomQuantity(Collection $lines): array
    {
        $total = '0.000000';
        $purchaseUom = null;
        $hasQuantity = false;
        $hasMixedUoms = false;

        foreach ($lines as $line) {
            if (! $line->purchaseOption) {
                continue;
            }

            $packQuantity = bcadd((string) $line->purchaseOption->pack_quantity, '0', 6);

            if (bccomp($packQuantity, '0.000000', 6) !== 1) {
                continue;
            }

            $lineQuantity = bcmul(
                bcadd((string) $line->pack_count, '0', 6),
                $packQuantity,
                6
            );

            if (bccomp($lineQuantity, '0.000000', 6) !== 1) {
                continue;
            }

            $lineUom = $line->purchaseOption->packUom;

            if ($purchaseUom === null) {
                $purchaseUom = $lineUom;
            } elseif (! $lineUom || (int) $lineUom->id !== (int) $purchaseUom->id) {
                $hasMixedUoms = true;
            }

            $total = bcadd($total, $lineQuantity, 6);
            $hasQuantity = true;
        }

        if (! $hasQuantity || $hasMixedUoms) {
            return [
                'quantity' => null,
                'uom' => null,
                'mixed' => $hasMixedUoms,
            ];
        }

        return [
            'quantity' => $total,
            'uom' => $purchaseUom,
            'mixed' => false,
        ];
    }

    /**
     * Resolve the material unit cost amount in order currency units.
     */
    private function unitCostAmount(int $lineTotalCents, ?string $totalQuantity): ?string
    {
        if ($totalQuantity === null) {
            return null;
        }

        if (bccomp($totalQuantity, '0', 6) <= 0) {
            return null;
        }

        $lineTotalAmount = bcdiv((string) $lineTotalCents, '100', 6);

        return bcdiv($lineTotalAmount, $totalQuantity, 6);
    }

    /**
     * Format an integer-cent amount without changing the canonical stored value.
     */
    private function formatMoneyCents(int $cents): string
    {
        return $this->formatGroupedQuantity(bcdiv((string) $cents, '100', 2));
    }

    /**
     * Format a decimal money amount for read-only display.
     */
    private function formatMoneyAmount(string $amount): string
    {
        return $this->formatGroupedQuantity(QuantityFormatter::format($amount, 2));
    }

    /**
     * Add thousands separators to an already formatted decimal quantity string.
     */
    private function formatGroupedQuantity(string $quantity): string
    {
        $trimmed = trim($quantity);

        if ($trimmed === '' || ! preg_match('/^-?\d+(?:\.\d+)?$/', $trimmed)) {
            return $quantity;
        }

        $isNegative = str_starts_with($trimmed, '-');
        $absolute = $isNegative ? substr($trimmed, 1) : $trimmed;
        [$wholePart, $fractionPart] = array_pad(explode('.', $absolute, 2), 2, '');
        $groupedWhole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $wholePart) ?? $wholePart;
        $formatted = $fractionPart === '' ? $groupedWhole : $groupedWhole . '.' . $fractionPart;

        return $isNegative ? '-' . $formatted : $formatted;
    }
}
