<?php

namespace App\Http\Controllers;

use App\Models\ItemPurchaseOption;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Support\QuantityFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Handle purchase order line mutations.
 */
class PurchaseOrderLineController extends Controller
{
    /**
     * Store a new purchase order line.
     */
    public function store(Request $request, int $purchaseOrderId): JsonResponse
    {
        Gate::authorize('purchasing-purchase-orders-create');

        $purchaseOrder = PurchaseOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($purchaseOrderId);

        if (! $purchaseOrder->isWorkflowEditable()) {
            return $this->lockedPurchaseOrderResponse();
        }

        $validator = Validator::make($request->all(), [
            'item_purchase_option_id' => [
                'required',
                'integer',
                Rule::exists('item_purchase_options', 'id')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('supplier_id', $purchaseOrder->supplier_id)
                    ->where('is_active', true),
            ],
            'item_id' => ['nullable', 'integer'],
            'pack_count' => ['required', 'integer', 'min:1'],
            'unit_price_cents' => ['required', 'integer', 'min:0'],
            'tax_percent' => ['nullable', 'regex:/^\d{1,3}(?:\.\d)?$/'],
        ]);

        $validator->after(function ($validator) use ($purchaseOrder, $request) {
            if (! $purchaseOrder->supplier_id) {
                $validator->errors()->add('supplier_id', 'Supplier must be selected before adding lines.');
            }

            if ($this->taxPercentToBasisPoints((string) $request->input('tax_percent', '0')) > 10000) {
                $validator->errors()->add('tax_percent', 'Tax percent must not exceed 100.');
            }
        });

        $validated = $validator->validate();

        $option = ItemPurchaseOption::query()
            ->with(['item', 'packUom'])
            ->where('id', $validated['item_purchase_option_id'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('is_active', true)
            ->firstOrFail();

        if (isset($validated['item_id']) && (int) $validated['item_id'] !== (int) $option->item_id) {
            return response()->json([
                'message' => 'Selected item does not match purchase option.',
                'errors' => [
                    'item_id' => ['Selected item does not match purchase option.'],
                ],
            ], 422);
        }

        $unitPriceCents = (int) $validated['unit_price_cents'];
        $packCount = (int) $validated['pack_count'];
        $lineSubtotal = $unitPriceCents * $packCount;
        $lineTaxRateBps = $this->taxPercentToBasisPoints($validated['tax_percent'] ?? null);
        $tenantCurrency = strtoupper(
            (string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD'))
        );
        $fxRate = number_format(1, 8, '.', '');
        $fxRateAsOf = $purchaseOrder->order_date
            ? $purchaseOrder->order_date->toDateString()
            : Carbon::today()->toDateString();

        $createdLine = null;
        $updatedOrder = null;

        DB::transaction(function () use (
            $purchaseOrder,
            $request,
            $option,
            $unitPriceCents,
            $packCount,
            $lineSubtotal,
            $lineTaxRateBps,
            $tenantCurrency,
            $fxRate,
            $fxRateAsOf,
            &$createdLine,
            &$updatedOrder
        ) {
            $lockedOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if (! $lockedOrder->isWorkflowEditable()) {
                return;
            }

            $createdLine = PurchaseOrderLine::query()->create([
                'tenant_id' => $request->user()->tenant_id,
                'purchase_order_id' => $lockedOrder->id,
                'item_id' => $option->item_id,
                'item_purchase_option_id' => $option->id,
                'pack_count' => $packCount,
                'unit_price_cents' => $unitPriceCents,
                'line_subtotal_cents' => $lineSubtotal,
                'line_tax_rate_bps' => $lineTaxRateBps,
                'unit_price_amount' => $unitPriceCents,
                'unit_price_currency_code' => $tenantCurrency,
                'converted_unit_price_amount' => $unitPriceCents,
                'fx_rate' => $fxRate,
                'fx_rate_as_of' => $fxRateAsOf,
            ]);

            $this->recalculateTotals($lockedOrder);
            $updatedOrder = $lockedOrder->fresh();
        });

        if (! $createdLine || ! $updatedOrder) {
            return $this->lockedPurchaseOrderResponse();
        }

        $createdLine->setRelation('item', $option->item);
        $createdLine->setRelation('purchaseOption', $option);
        $option->setRelation('packUom', $option->packUom);

        return response()->json([
            'data' => [
                'line' => $this->linePayload($createdLine, $tenantCurrency),
                'purchase_order' => $this->purchaseOrderTotalsPayload($updatedOrder),
            ],
        ], 201);
    }

    /**
     * Update an existing purchase order line.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderLine $line): JsonResponse
    {
        Gate::authorize('purchasing-purchase-orders-create');

        if ($line->purchase_order_id !== $purchaseOrder->id) {
            abort(404);
        }

        if (! $purchaseOrder->isWorkflowEditable()) {
            return $this->lockedPurchaseOrderResponse();
        }

        $validator = Validator::make($request->all(), [
            'pack_count' => ['required', 'integer', 'min:1'],
            'unit_price_cents' => ['required', 'integer', 'min:0'],
            'tax_percent' => ['nullable', 'regex:/^\d{1,3}(?:\.\d)?$/'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            if ($this->taxPercentToBasisPoints((string) $request->input('tax_percent', '0')) > 10000) {
                $validator->errors()->add('tax_percent', 'Tax percent must not exceed 100.');
            }
        });

        $validated = $validator->validate();

        $unitPriceCents = (int) $validated['unit_price_cents'];
        $packCount = (int) $validated['pack_count'];
        $lineSubtotal = $unitPriceCents * $packCount;
        $lineTaxRateBps = $this->taxPercentToBasisPoints($validated['tax_percent'] ?? null);
        $tenantCurrency = strtoupper(
            (string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD'))
        );

        $updatedLine = null;
        $updatedOrder = null;

        DB::transaction(function () use (
            $purchaseOrder,
            $line,
            $unitPriceCents,
            $packCount,
            $lineSubtotal,
            $lineTaxRateBps,
            &$updatedLine,
            &$updatedOrder
        ) {
            $lockedOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if (! $lockedOrder->isWorkflowEditable()) {
                return;
            }

            $line->update([
                'pack_count' => $packCount,
                'unit_price_cents' => $unitPriceCents,
                'line_subtotal_cents' => $lineSubtotal,
                'line_tax_rate_bps' => $lineTaxRateBps,
                'unit_price_amount' => $unitPriceCents,
                'converted_unit_price_amount' => $unitPriceCents,
            ]);

            $this->recalculateTotals($lockedOrder);
            $updatedLine = $line->fresh(['item', 'purchaseOption.packUom']);
            $updatedOrder = $lockedOrder->fresh();
        });

        if (! $updatedLine || ! $updatedOrder) {
            return $this->lockedPurchaseOrderResponse();
        }

        return response()->json([
            'data' => [
                'line' => $this->linePayload($updatedLine, $tenantCurrency),
                'purchase_order' => $this->purchaseOrderTotalsPayload($updatedOrder),
            ],
        ]);
    }

    /**
     * Delete a purchase order line.
     */
    public function destroy(Request $request, int $purchaseOrderId, int $lineId): JsonResponse
    {
        Gate::authorize('purchasing-purchase-orders-create');

        $purchaseOrder = PurchaseOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($purchaseOrderId);

        if (! $purchaseOrder->isWorkflowEditable()) {
            return $this->lockedPurchaseOrderResponse();
        }

        $line = PurchaseOrderLine::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($lineId);

        if ($line->purchase_order_id !== $purchaseOrder->id) {
            abort(404);
        }

        $updatedOrder = null;
        $remainingLines = [];
        $tenantCurrency = strtoupper(
            (string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD'))
        );

        DB::transaction(function () use ($purchaseOrder, $line, $tenantCurrency, &$updatedOrder, &$remainingLines) {
            $lockedOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if (! $lockedOrder->isWorkflowEditable()) {
                return;
            }

            $line->delete();
            $this->recalculateTotals($lockedOrder);
            $updatedOrder = $lockedOrder->fresh(['lines.item', 'lines.purchaseOption.packUom']);
            $remainingLines = $updatedOrder->lines
                ->map(fn (PurchaseOrderLine $remainingLine): array => $this->linePayload($remainingLine, $tenantCurrency))
                ->values()
                ->all();
        });

        if (! $updatedOrder) {
            return $this->lockedPurchaseOrderResponse();
        }

        return response()->json([
            'data' => [
                'purchase_order' => $this->purchaseOrderTotalsPayload($updatedOrder),
                'lines' => $remainingLines,
            ],
        ]);
    }

    /**
     * Build line payloads for the UI.
     */
    private function linePayload(PurchaseOrderLine $line, string $tenantCurrency): array
    {
        $option = $line->purchaseOption;
        $packCount = bcadd((string) $line->pack_count, '0', 6);
        $packQuantity = $option ? bcadd((string) $option->pack_quantity, '0', 6) : null;
        $packPrecision = (int) ($option?->packUom?->display_precision ?? 1);

        return [
            'id' => $line->id,
            'item_id' => $line->item_id,
            'item_name' => $line->item?->name,
            'item_purchase_option_id' => $line->item_purchase_option_id,
            'pack_count' => (int) $line->pack_count,
            'pack_count_display' => QuantityFormatter::format($packCount, $packPrecision),
            'unit_price_cents' => $line->unit_price_cents,
            'line_subtotal_cents' => $line->line_subtotal_cents,
            'tax_percent' => $this->basisPointsToTaxPercent((int) $line->line_tax_rate_bps),
            'line_tax_rate_bps' => (int) $line->line_tax_rate_bps,
            'pack_quantity' => $packQuantity,
            'pack_quantity_display' => $packQuantity !== null
                ? QuantityFormatter::format($packQuantity, $packPrecision)
                : null,
            'pack_precision' => $packPrecision,
            'pack_uom_symbol' => $option?->packUom?->symbol,
            'pack_uom_name' => $option?->packUom?->name,
            'received_sum' => '0.000000',
            'received_sum_display' => QuantityFormatter::format('0.000000', $packPrecision),
            'short_closed_sum' => '0.000000',
            'short_closed_sum_display' => QuantityFormatter::format('0.000000', $packPrecision),
            'remaining_balance' => $packCount,
            'remaining_balance_display' => QuantityFormatter::format($packCount, $packPrecision),
            'currency_code' => $tenantCurrency,
        ];
    }

    /**
     * Build totals payload for the UI.
     */
    private function purchaseOrderTotalsPayload(PurchaseOrder $purchaseOrder): array
    {
        return [
            'po_subtotal_cents' => $purchaseOrder->po_subtotal_cents,
            'po_grand_total_cents' => $purchaseOrder->po_grand_total_cents,
            'shipping_cents' => $purchaseOrder->shipping_cents,
            'tax_cents' => $purchaseOrder->tax_cents,
        ];
    }

    /**
     * Recalculate purchase order totals from line items.
     */
    private function recalculateTotals(PurchaseOrder $purchaseOrder): void
    {
        $subtotal = (int) PurchaseOrderLine::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->sum('line_subtotal_cents');

        $shipping = $purchaseOrder->shipping_cents ?? 0;
        $tax = (int) PurchaseOrderLine::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->get(['line_subtotal_cents', 'line_tax_rate_bps'])
            ->sum(fn (PurchaseOrderLine $line): int => $this->taxCentsForLine(
                (int) $line->line_subtotal_cents,
                (int) $line->line_tax_rate_bps
            ));

        $purchaseOrder->forceFill([
            'po_subtotal_cents' => $subtotal,
            'tax_cents' => $tax,
            'po_grand_total_cents' => $subtotal + $shipping + $tax,
        ])->save();
    }

    /**
     * Build a JSON response for locked purchase order line edits.
     */
    private function lockedPurchaseOrderResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Purchase order is locked for editing.',
            'errors' => [
                'purchase_order' => ['Purchase order is locked for editing.'],
            ],
        ], 422);
    }

    /**
     * Convert tax percentage text into basis points.
     */
    private function taxPercentToBasisPoints(?string $taxPercent): int
    {
        if ($taxPercent === null || $taxPercent === '') {
            return 0;
        }

        $parts = explode('.', $taxPercent, 2);
        $whole = (int) $parts[0];
        $fraction = (int) ($parts[1] ?? '0');

        return ($whole * 100) + ($fraction * 10);
    }

    /**
     * Convert basis points into displayable tax percentage text.
     */
    private function basisPointsToTaxPercent(int $basisPoints): string
    {
        $tenths = intdiv($basisPoints + 5, 10);
        $whole = intdiv($tenths, 10);
        $fraction = $tenths % 10;

        return $whole . '.' . $fraction;
    }

    /**
     * Calculate line tax cents using integer basis points.
     */
    private function taxCentsForLine(int $lineSubtotalCents, int $lineTaxRateBps): int
    {
        return intdiv(($lineSubtotalCents * $lineTaxRateBps) + 5000, 10000);
    }
}
