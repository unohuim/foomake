<?php

namespace App\Http\Controllers;

use App\Models\ItemPurchaseOption;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Support\QuantityFormatter;
use App\Services\Purchasing\PurchaseOrderLifecycleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Handle purchase order screens and header updates.
 */
class PurchaseOrderController extends Controller
{
    /**
     * Display the purchase orders index.
     */
    public function index(Request $request, PurchaseOrderLifecycleService $lifecycleService): View
    {
        Gate::authorize('purchasing-purchase-orders-create');

        $purchaseOrders = PurchaseOrder::query()
            ->with('supplier')
            ->with('lines')
            ->with('lines.item')
            ->with('lines.purchaseOption.packUom')
            ->withCount('lines')
            ->orderByDesc('created_at')
            ->get();

        $tenantCurrency = strtoupper(
            (string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD'))
        );

        $canReceive = Gate::allows('purchasing-purchase-orders-receive');

        $lineTotalsByOrder = [];

        foreach ($purchaseOrders as $purchaseOrder) {
            $lineTotalsByOrder[$purchaseOrder->id] = $lifecycleService->computeLineTotals($purchaseOrder);
        }

        return view('purchasing.orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'tenantCurrency' => $tenantCurrency,
            'canReceive' => $canReceive,
            'lineTotalsByOrder' => $lineTotalsByOrder,
        ]);
    }

    /**
     * Store a new draft purchase order.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('purchasing-purchase-orders-create');

        $validated = $request->validate([
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'order_date' => ['nullable', 'date'],
            'shipping_cents' => ['nullable', 'integer', 'min:0'],
            'tax_cents' => ['nullable', 'integer', 'min:0'],
            'po_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $orderDate = $validated['order_date'] ?? null;

        $purchaseOrder = PurchaseOrder::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'created_by_user_id' => $request->user()->id,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'order_date' => $orderDate ? Carbon::parse($orderDate)->toDateString() : null,
            'shipping_cents' => $validated['shipping_cents'] ?? null,
            'tax_cents' => $validated['tax_cents'] ?? null,
            'po_number' => $validated['po_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'po_subtotal_cents' => 0,
            'po_grand_total_cents' => 0,
        ]);

        return response()->json([
            'data' => [
                'id' => $purchaseOrder->id,
                'show_url' => route('purchasing.orders.show', $purchaseOrder),
            ],
        ], 201);
    }

    /**
     * Display a purchase order detail page.
     */
    public function show(
        Request $request,
        PurchaseOrder $purchaseOrder,
        PurchaseOrderLifecycleService $lifecycleService
    ): View
    {
        Gate::authorize('purchasing-purchase-orders-create');

        $purchaseOrder->load([
            'supplier',
            'lines',
            'lines.item',
            'lines.purchaseOption.packUom',
            'receipts',
            'receipts.lines',
            'receipts.receivedByUser',
            'shortClosures',
            'shortClosures.lines',
            'shortClosures.shortClosedByUser',
        ]);

        $tenantCurrency = strtoupper(
            (string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD'))
        );

        $suppliers = Supplier::query()
            ->orderBy('company_name')
            ->get(['id', 'company_name']);

        $purchaseOptions = ItemPurchaseOption::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['item', 'packUom', 'currentPrice'])
            ->orderBy('id')
            ->get();

        $lineTotals = $lifecycleService->computeLineTotals($purchaseOrder);
        $canReceive = Gate::allows('purchasing-purchase-orders-receive');

        $payload = [
            'purchaseOrder' => $this->purchaseOrderPayload($purchaseOrder),
            'lines' => $purchaseOrder->lines->map(function (PurchaseOrderLine $line) use ($tenantCurrency, $lineTotals) {
                return $this->linePayload($line, $tenantCurrency, $lineTotals[$line->id] ?? []);
            })->values()->all(),
            'suppliers' => $suppliers->map(function (Supplier $supplier) {
                return [
                    'id' => $supplier->id,
                    'company_name' => $supplier->company_name,
                ];
            })->values()->all(),
            'purchaseOptions' => $purchaseOptions->map(function (ItemPurchaseOption $option) use ($tenantCurrency) {
                $currentPrice = $option->currentPrice;
                $packQuantity = bcadd((string) $option->pack_quantity, '0', 6);
                $packPrecision = (int) ($option->packUom?->display_precision ?? 1);

                return [
                    'id' => $option->id,
                    'supplier_id' => $option->supplier_id,
                    'item_id' => $option->item_id,
                    'item_name' => $option->item?->name,
                    'pack_quantity' => $packQuantity,
                    'pack_quantity_display' => QuantityFormatter::format($packQuantity, $packPrecision),
                    'pack_precision' => $packPrecision,
                    'pack_uom_symbol' => $option->packUom?->symbol,
                    'pack_uom_name' => $option->packUom?->name,
                    'current_price_cents' => $currentPrice?->converted_price_cents ?? 0,
                    'currency_code' => $tenantCurrency,
                ];
            })->values()->all(),
            'receipts' => $this->receiptHistoryPayload($purchaseOrder),
            'shortClosures' => $this->shortClosureHistoryPayload($purchaseOrder),
            'tenantCurrency' => $tenantCurrency,
            'updateUrl' => route('purchasing.orders.update', $purchaseOrder),
            'deleteUrl' => route('purchasing.orders.destroy', $purchaseOrder),
            'lineStoreUrl' => route('purchasing.orders.lines.store', $purchaseOrder),
            'lineUpdateUrlBase' => url("/purchasing/orders/{$purchaseOrder->id}/lines"),
            'lineDeleteUrlBase' => url("/purchasing/orders/{$purchaseOrder->id}/lines"),
            'receiptStoreUrl' => route('purchasing.orders.receipts.store', $purchaseOrder),
            'shortCloseStoreUrl' => route('purchasing.orders.short-closures.store', $purchaseOrder),
            'statusUpdateUrl' => route('purchasing.orders.status.update', $purchaseOrder),
            'indexUrl' => route('purchasing.orders.index'),
            'canReceive' => $canReceive,
            'currentUserName' => $request->user()?->name,
            'csrfToken' => csrf_token(),
        ];

        return view('purchasing.orders.show', [
            'purchaseOrder' => $purchaseOrder,
            'payload' => $payload,
        ]);
    }

    /**
     * Update purchase order header fields.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('purchasing-purchase-orders-create');

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only draft purchase orders can be edited.',
            ], 422);
        }

        $validated = $request->validate([
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'order_date' => ['nullable', 'date'],
            'shipping_cents' => ['nullable', 'integer', 'min:0'],
            'tax_cents' => ['nullable', 'integer', 'min:0'],
            'po_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $payload = $request->all();
        $updateData = [];

        if (array_key_exists('supplier_id', $payload)) {
            $updateData['supplier_id'] = $validated['supplier_id'] ?? null;
        }

        if (array_key_exists('order_date', $payload)) {
            $orderDate = $validated['order_date'] ?? null;
            $updateData['order_date'] = $orderDate ? Carbon::parse($orderDate)->toDateString() : null;
        }

        if (array_key_exists('shipping_cents', $payload)) {
            $updateData['shipping_cents'] = $validated['shipping_cents'] ?? null;
        }

        if (array_key_exists('tax_cents', $payload)) {
            $updateData['tax_cents'] = $validated['tax_cents'] ?? null;
        }

        if (array_key_exists('po_number', $payload)) {
            $updateData['po_number'] = $validated['po_number'] ?? null;
        }

        if (array_key_exists('notes', $payload)) {
            $updateData['notes'] = $validated['notes'] ?? null;
        }

        $updatedOrder = null;

        DB::transaction(function () use ($purchaseOrder, $updateData, &$updatedOrder) {
            $lockedOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if ($lockedOrder->status !== PurchaseOrder::STATUS_DRAFT) {
                $updatedOrder = $lockedOrder;
                return;
            }

            if ($updateData !== []) {
                $lockedOrder->update($updateData);
            }

            $this->recalculateTotals($lockedOrder);
            $updatedOrder = $lockedOrder->fresh(['supplier']);
        });

        if (! $updatedOrder || $updatedOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only draft purchase orders can be edited.',
            ], 422);
        }

        return response()->json([
            'data' => $this->purchaseOrderPayload($updatedOrder),
        ]);
    }

    /**
     * Delete a draft purchase order.
     */
    public function destroy(Request $request, int $purchaseOrderId): JsonResponse
    {
        Gate::authorize('purchasing-purchase-orders-create');

        $purchaseOrder = PurchaseOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($purchaseOrderId);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only draft purchase orders can be deleted.',
            ], 422);
        }

        $purchaseOrder->delete();

        return response()->json([
            'message' => 'Deleted.',
        ]);
    }

    /**
     * Build purchase order payloads for the UI.
     */
    private function purchaseOrderPayload(PurchaseOrder $purchaseOrder): array
    {
        return [
            'id' => $purchaseOrder->id,
            'supplier_id' => $purchaseOrder->supplier_id,
            'supplier_name' => $purchaseOrder->supplier?->company_name,
            'order_date' => $purchaseOrder->order_date?->format('Y-m-d'),
            'shipping_cents' => $purchaseOrder->shipping_cents,
            'tax_cents' => $purchaseOrder->tax_cents,
            'po_number' => $purchaseOrder->po_number,
            'notes' => $purchaseOrder->notes,
            'status' => $purchaseOrder->status,
            'po_subtotal_cents' => $purchaseOrder->po_subtotal_cents,
            'po_grand_total_cents' => $purchaseOrder->po_grand_total_cents,
        ];
    }

    /**
     * Build line payloads for the UI.
     */
    private function linePayload(PurchaseOrderLine $line, string $tenantCurrency, array $lineTotals = []): array
    {
        $option = $line->purchaseOption;
        $packCount = bcadd((string) $line->pack_count, '0', 6);
        $packQuantity = $option ? bcadd((string) $option->pack_quantity, '0', 6) : null;
        $packPrecision = (int) ($option?->packUom?->display_precision ?? 1);
        $receivedSum = $lineTotals['received_sum'] ?? '0.000000';
        $shortClosedSum = $lineTotals['short_closed_sum'] ?? '0.000000';
        $balance = $lineTotals['balance'] ?? $packCount;

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
            'received_sum' => $receivedSum,
            'received_sum_display' => QuantityFormatter::format($receivedSum, $packPrecision),
            'short_closed_sum' => $shortClosedSum,
            'short_closed_sum_display' => QuantityFormatter::format($shortClosedSum, $packPrecision),
            'remaining_balance' => $balance,
            'remaining_balance_display' => QuantityFormatter::format($balance, $packPrecision),
            'currency_code' => $tenantCurrency,
        ];
    }

    /**
     * Build receipt history payloads for the UI.
     */
    private function receiptHistoryPayload(PurchaseOrder $purchaseOrder): array
    {
        return $purchaseOrder->receipts
            ->sortByDesc('received_at')
            ->map(function ($receipt) {
                $total = '0.000000';

                foreach ($receipt->lines as $line) {
                    $total = bcadd($total, (string) $line->received_quantity, 6);
                }

                return [
                    'id' => $receipt->id,
                    'received_at' => $receipt->received_at?->format('Y-m-d H:i:s'),
                    'received_by' => $receipt->receivedByUser?->name,
                    'reference' => $receipt->reference,
                    'notes' => $receipt->notes,
                    'lines_count' => $receipt->lines->count(),
                    'total_packs' => $total,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build short-close history payloads for the UI.
     */
    private function shortClosureHistoryPayload(PurchaseOrder $purchaseOrder): array
    {
        return $purchaseOrder->shortClosures
            ->sortByDesc('short_closed_at')
            ->map(function ($shortClosure) {
                $total = '0.000000';

                foreach ($shortClosure->lines as $line) {
                    $total = bcadd($total, (string) $line->short_closed_quantity, 6);
                }

                return [
                    'id' => $shortClosure->id,
                    'short_closed_at' => $shortClosure->short_closed_at?->format('Y-m-d H:i:s'),
                    'short_closed_by' => $shortClosure->shortClosedByUser?->name,
                    'reference' => $shortClosure->reference,
                    'notes' => $shortClosure->notes,
                    'lines_count' => $shortClosure->lines->count(),
                    'total_packs' => $total,
                ];
            })
            ->values()
            ->all();
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
