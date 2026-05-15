<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MaterialDraftPurchaseOrderController extends Controller
{
    /**
     * Store a draft purchase order with one line from a material supplier package context.
     */
    public function store(Request $request, Item $item): JsonResponse
    {
        Gate::authorize('inventory-materials-view');
        Gate::authorize('purchasing-purchase-orders-create');

        $validated = $request->validate([
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'item_purchase_option_id' => [
                'required',
                'integer',
                Rule::exists('item_purchase_options', 'id')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('is_active', true),
            ],
            'pack_count' => ['required', 'integer', 'min:1'],
        ]);

        if (! $item->is_purchasable) {
            return response()->json([
                'message' => 'Material is not purchasable.',
                'errors' => [
                    'item_id' => ['Material is not purchasable.'],
                ],
            ], 422);
        }

        $option = ItemPurchaseOption::query()
            ->with(['currentPrice', 'supplier'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('is_active', true)
            ->findOrFail((int) $validated['item_purchase_option_id']);

        if ((int) $option->item_id !== (int) $item->id) {
            return response()->json([
                'message' => 'Selected supplier package does not belong to this material.',
                'errors' => [
                    'item_purchase_option_id' => ['Selected supplier package does not belong to this material.'],
                ],
            ], 422);
        }

        if (! $option->supplier_id || ! $option->supplier) {
            return response()->json([
                'message' => 'Selected supplier package is invalid.',
                'errors' => [
                    'item_purchase_option_id' => ['Selected supplier package is invalid.'],
                ],
            ], 422);
        }

        if (
            array_key_exists('supplier_id', $validated)
            && $validated['supplier_id'] !== null
            && (int) $validated['supplier_id'] !== (int) $option->supplier_id
        ) {
            return response()->json([
                'message' => 'Selected supplier does not match supplier package.',
                'errors' => [
                    'supplier_id' => ['Selected supplier does not match supplier package.'],
                ],
            ], 422);
        }

        if (! $option->currentPrice || $option->currentPrice->converted_price_cents === null) {
            return response()->json([
                'message' => 'Selected supplier package has no current price.',
                'errors' => [
                    'item_purchase_option_id' => ['Selected supplier package has no current price.'],
                ],
            ], 422);
        }

        $packCount = (int) $validated['pack_count'];
        $unitPriceCents = (int) $option->currentPrice->converted_price_cents;
        $lineSubtotal = $unitPriceCents * $packCount;
        $tenantCurrency = strtoupper(
            (string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD'))
        );
        $fxRate = number_format(1, 8, '.', '');
        $fxRateAsOf = Carbon::today()->toDateString();

        $purchaseOrder = null;

        DB::transaction(function () use (
            $request,
            $item,
            $option,
            $packCount,
            $unitPriceCents,
            $lineSubtotal,
            $tenantCurrency,
            $fxRate,
            $fxRateAsOf,
            &$purchaseOrder
        ): void {
            $purchaseOrder = PurchaseOrder::query()->create([
                'tenant_id' => $request->user()->tenant_id,
                'created_by_user_id' => $request->user()->id,
                'supplier_id' => $option->supplier_id,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'po_subtotal_cents' => 0,
                'po_grand_total_cents' => 0,
            ]);

            PurchaseOrderLine::query()->create([
                'tenant_id' => $request->user()->tenant_id,
                'purchase_order_id' => $purchaseOrder->id,
                'item_id' => $item->id,
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

            $this->recalculateTotals($purchaseOrder);
            $purchaseOrder = $purchaseOrder->fresh();
        });

        return response()->json([
            'data' => [
                'id' => $purchaseOrder->id,
                'show_url' => route('purchasing.orders.show', $purchaseOrder),
            ],
        ], 201);
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
