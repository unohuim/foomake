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

        if (! in_array($purchaseOrder->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_OPEN], true)) {
            return response()->json([
                'message' => 'Only draft or open purchase orders can be edited.',
            ], 422);
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
        ]);

        $validator->after(function ($validator) use ($purchaseOrder) {
            if (! $purchaseOrder->supplier_id) {
                $validator->errors()->add('supplier_id', 'Supplier must be selected before adding lines.');
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
            $tenantCurrency,
            $fxRate,
            $fxRateAsOf,
            &$createdLine,
            &$updatedOrder
        ) {
            $lockedOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if (! in_array($lockedOrder->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_OPEN], true)) {
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
            return response()->json([
                'message' => 'Only draft or open purchase orders can be edited.',
            ], 422);
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

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only draft purchase orders can be edited.',
            ], 422);
        }

        $validated = $request->validate([
            'pack_count' => ['required', 'integer', 'min:1'],
            'unit_price_cents' => ['required', 'integer', 'min:0'],
        ]);

        $unitPriceCents = (int) $validated['unit_price_cents'];
        $packCount = (int) $validated['pack_count'];
        $lineSubtotal = $unitPriceCents * $packCount;
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
            &$updatedLine,
            &$updatedOrder
        ) {
            $lockedOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if ($lockedOrder->status !== PurchaseOrder::STATUS_DRAFT) {
                return;
            }

            $line->update([
                'pack_count' => $packCount,
                'unit_price_cents' => $unitPriceCents,
                'line_subtotal_cents' => $lineSubtotal,
                'unit_price_amount' => $unitPriceCents,
                'converted_unit_price_amount' => $unitPriceCents,
            ]);

            $this->recalculateTotals($lockedOrder);
            $updatedLine = $line->fresh(['item', 'purchaseOption.packUom']);
            $updatedOrder = $lockedOrder->fresh();
        });

        if (! $updatedLine || ! $updatedOrder) {
            return response()->json([
                'message' => 'Only draft purchase orders can be edited.',
            ], 422);
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

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only draft purchase orders can be edited.',
            ], 422);
        }

        $line = PurchaseOrderLine::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($lineId);

        if ($line->purchase_order_id !== $purchaseOrder->id) {
            abort(404);
        }

        $updatedOrder = null;

        DB::transaction(function () use ($purchaseOrder, $line, &$updatedOrder) {
            $lockedOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if ($lockedOrder->status !== PurchaseOrder::STATUS_DRAFT) {
                return;
            }

            $line->delete();
            $this->recalculateTotals($lockedOrder);
            $updatedOrder = $lockedOrder->fresh();
        });

        if (! $updatedOrder) {
            return response()->json([
                'message' => 'Only draft purchase orders can be edited.',
            ], 422);
        }

        return response()->json([
            'data' => [
                'purchase_order' => $this->purchaseOrderTotalsPayload($updatedOrder),
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
            'pack_count' => $packCount,
            'pack_count_display' => QuantityFormatter::format($packCount, $packPrecision),
            'unit_price_cents' => $line->unit_price_cents,
            'line_subtotal_cents' => $line->line_subtotal_cents,
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
        $tax = $purchaseOrder->tax_cents ?? 0;

        $purchaseOrder->forceFill([
            'po_subtotal_cents' => $subtotal,
            'po_grand_total_cents' => $subtotal + $shipping + $tax,
        ])->save();
    }
}
