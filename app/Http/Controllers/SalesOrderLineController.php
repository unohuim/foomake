<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sales\StoreSalesOrderLineRequest;
use App\Http\Requests\Sales\UpdateSalesOrderLineRequest;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Handle draft sales order line mutations.
 */
class SalesOrderLineController extends Controller
{
    private const SCALE = 6;

    /**
     * Store a new sales order line.
     */
    public function store(StoreSalesOrderLineRequest $request, SalesOrder $salesOrder): JsonResponse
    {
        Gate::authorize('sales-sales-orders-manage');

        if ($salesOrder->status !== SalesOrder::STATUS_DRAFT) {
            return $this->draftOnlyResponse();
        }

        $item = Item::query()->findOrFail((int) $request->validated('item_id'));
        $quantity = $this->normalizeQuantity((string) $request->validated('quantity'));
        $unitPriceCents = (int) ($item->default_price_cents ?? 0);
        $unitPriceCurrencyCode = strtoupper(
            (string) ($item->default_price_currency_code ?: $request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD'))
        );

        $line = SalesOrderLine::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'sales_order_id' => $salesOrder->id,
            'item_id' => $item->id,
            'quantity' => $quantity,
            'unit_price_cents' => $unitPriceCents,
            'unit_price_currency_code' => $unitPriceCurrencyCode,
            'line_total_cents' => $this->calculateLineTotalCents($quantity, $unitPriceCents),
        ]);

        $line->load('item');
        $salesOrder->load(['customer', 'contact', 'lines.item']);

        return response()->json([
            'data' => [
                'line' => $this->lineData($line),
                'order' => $this->orderData($salesOrder),
            ],
        ], 201);
    }

    /**
     * Update the quantity for an existing sales order line.
     */
    public function update(
        UpdateSalesOrderLineRequest $request,
        SalesOrder $salesOrder,
        SalesOrderLine $line
    ): JsonResponse {
        Gate::authorize('sales-sales-orders-manage');

        if ($line->sales_order_id !== $salesOrder->id) {
            abort(404);
        }

        if ($salesOrder->status !== SalesOrder::STATUS_DRAFT) {
            return $this->draftOnlyResponse();
        }

        $quantity = $this->normalizeQuantity((string) $request->validated('quantity'));

        $line->update([
            'quantity' => $quantity,
            'line_total_cents' => $this->calculateLineTotalCents($quantity, (int) $line->unit_price_cents),
        ]);

        $line->load('item');
        $salesOrder->load(['customer', 'contact', 'lines.item']);

        return response()->json([
            'data' => [
                'line' => $this->lineData($line),
                'order' => $this->orderData($salesOrder),
            ],
        ]);
    }

    /**
     * Delete an existing sales order line.
     */
    public function destroy(SalesOrder $salesOrder, SalesOrderLine $line): JsonResponse
    {
        Gate::authorize('sales-sales-orders-manage');

        if ($line->sales_order_id !== $salesOrder->id) {
            abort(404);
        }

        if ($salesOrder->status !== SalesOrder::STATUS_DRAFT) {
            return $this->draftOnlyResponse();
        }

        $deletedLineId = $line->id;
        $line->delete();

        $salesOrder->load(['customer', 'contact', 'lines.item']);

        return response()->json([
            'data' => [
                'deleted_line_id' => $deletedLineId,
                'order' => $this->orderData($salesOrder),
            ],
        ]);
    }

    /**
     * Build the shared sales order payload.
     *
     * @return array<string, int|string|null|array<int, array<string, int|string|null>>>
     */
    public function orderData(SalesOrder $order): array
    {
        $contactName = null;

        if ($order->contact) {
            $contactName = $order->contact->full_name;
        }

        $orderTotalCents = '0.000000';
        $lines = $order->lines->map(function (SalesOrderLine $line) use (&$orderTotalCents): array {
            $orderTotalCents = bcadd($orderTotalCents, (string) $line->line_total_cents, self::SCALE);

            return $this->lineData($line);
        })->values()->all();

        return [
            'id' => $order->id,
            'customer_id' => $order->customer_id,
            'customer_name' => $order->customer?->name,
            'contact_id' => $order->contact_id,
            'contact_name' => $contactName,
            'status' => $order->status,
            'lines' => $lines,
            'line_count' => count($lines),
            'order_total_cents' => $orderTotalCents,
            'order_total_amount' => bcdiv($orderTotalCents, '100', self::SCALE),
        ];
    }

    /**
     * Build the sales order line response payload.
     *
     * @return array<string, int|string|null>
     */
    public function lineData(SalesOrderLine $line): array
    {
        $unitPriceAmount = bcdiv((string) $line->unit_price_cents, '100', 2);
        $lineTotalCents = (string) $line->line_total_cents;

        return [
            'id' => $line->id,
            'item_id' => $line->item_id,
            'item_name' => $line->item?->name,
            'quantity' => (string) $line->quantity,
            'unit_price_cents' => $line->unit_price_cents,
            'unit_price_currency_code' => $line->unit_price_currency_code,
            'unit_price_amount' => $unitPriceAmount,
            'line_total_cents' => $lineTotalCents,
            'line_total_amount' => bcdiv($lineTotalCents, '100', self::SCALE),
        ];
    }

    /**
     * Return the shared draft-only mutation response.
     */
    private function draftOnlyResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Only draft sales orders can be edited.',
            'errors' => [
                'item_id' => [],
                'quantity' => [],
            ],
        ], 422);
    }

    /**
     * Normalize a quantity to the canonical scale.
     */
    private function normalizeQuantity(string $quantity): string
    {
        return bcadd($quantity, '0', self::SCALE);
    }

    /**
     * Calculate the line total in minor currency units.
     */
    private function calculateLineTotalCents(string $quantity, int $unitPriceCents): string
    {
        return bcmul($quantity, (string) $unitPriceCents, self::SCALE);
    }
}
