<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Support\QuantityFormatter;
use App\Services\Purchasing\PurchaseOrderLifecycleService;
use App\Services\Workflows\PurchaseOrderWorkflow;
use App\Support\Workflows\WorkflowAssignmentPermissions;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderShortClosureController extends Controller
{
    private const SCALE = 6;

    /**
     * Store a new purchase order short-closure event.
     */
    public function store(
        Request $request,
        int $purchaseOrder,
        PurchaseOrderLifecycleService $lifecycleService,
        PurchaseOrderWorkflow $purchaseOrderWorkflow
    ): JsonResponse {
        abort_unless(
            app(WorkflowAssignmentPermissions::class)->userCanOperateWorkflowDomain($request->user(), 'purchasing'),
            403
        );

        $purchaseOrder = PurchaseOrder::query()->findOrFail($purchaseOrder);

        $hasLinesArray = is_array($request->input('lines'));

        $rules = [
            'short_closed_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];

        if ($hasLinesArray) {
            $rules['lines'] = ['required', 'array', 'min:1'];
            $rules['lines.*.purchase_order_line_id'] = ['required', 'integer'];
            $rules['lines.*.short_closed_quantity'] = ['required', 'numeric'];
        } else {
            $rules['purchase_order_line_id'] = ['required', 'integer'];
            $rules['short_closed_quantity'] = ['required', 'numeric'];
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request, $purchaseOrder, $hasLinesArray, $lifecycleService) {
            $lineInputs = $hasLinesArray ? (array) $request->input('lines') : [[
                'purchase_order_line_id' => $request->input('purchase_order_line_id'),
                'short_closed_quantity' => $request->input('short_closed_quantity'),
            ]];

            if ($hasLinesArray && count($lineInputs) === 0) {
                $validator->errors()->add('lines', 'At least one line is required.');
                return;
            }

            $lineIds = collect($lineInputs)
                ->pluck('purchase_order_line_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $orderLines = $purchaseOrder->lines()
                ->whereIn('id', $lineIds)
                ->get()
                ->keyBy('id');

            $lineTotals = $lifecycleService->computeLineTotals($purchaseOrder);

            foreach ($lineInputs as $index => $lineInput) {
                $prefix = $hasLinesArray ? "lines.{$index}." : '';
                $lineId = (int) ($lineInput['purchase_order_line_id'] ?? 0);
                $quantity = (string) ($lineInput['short_closed_quantity'] ?? '');

                if (! $lineId || ! $orderLines->has($lineId)) {
                    $validator->errors()->add("{$prefix}purchase_order_line_id", 'Line is invalid for this purchase order.');
                    continue;
                }

                if (bccomp($quantity, '0', self::SCALE) <= 0) {
                    $validator->errors()->add("{$prefix}short_closed_quantity", 'Quantity must be greater than zero.');
                    continue;
                }

                $balance = $lineTotals[$lineId]['balance'] ?? '0.000000';

                if (bccomp($quantity, $balance, self::SCALE) === 1) {
                    $validator->errors()->add("{$prefix}short_closed_quantity", 'Quantity exceeds remaining balance.');
                }
            }
        });

        $validated = $validator->validate();

        $lineInputs = $hasLinesArray ? (array) $request->input('lines') : [[
            'purchase_order_line_id' => $validated['purchase_order_line_id'] ?? null,
            'short_closed_quantity' => $validated['short_closed_quantity'] ?? null,
        ]];

        $lineIds = collect($lineInputs)
            ->pluck('purchase_order_line_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $orderLines = $purchaseOrder->lines()
            ->whereIn('id', $lineIds)
            ->get()
            ->keyBy('id');

        $lineItems = [];

        foreach ($lineInputs as $lineInput) {
            $lineId = (int) $lineInput['purchase_order_line_id'];
            $line = $orderLines->get($lineId);

            if (! $line) {
                continue;
            }

            $lineItems[] = [
                'line' => $line,
                'quantity' => (string) $lineInput['short_closed_quantity'],
            ];
        }

        try {
            $shortClosure = $lifecycleService->createShortClosure(
                $purchaseOrder,
                $request->user(),
                $lineItems,
                $validated['short_closed_at'] ?? null,
                $validated['reference'] ?? null,
                $validated['notes'] ?? null
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'status' => [$exception->getMessage()],
                ],
            ], 422);
        }

        return response()->json([
            'data' => $this->shortClosureResponsePayload(
                $shortClosure->id,
                $purchaseOrder,
                $lifecycleService,
                $purchaseOrderWorkflow,
                $request
            ),
        ], 201);
    }

    /**
     * Build the short-close success payload consumed by the PO detail page.
     *
     * @return array<string, mixed>
     */
    private function shortClosureResponsePayload(
        int $shortClosureId,
        PurchaseOrder $purchaseOrder,
        PurchaseOrderLifecycleService $lifecycleService,
        PurchaseOrderWorkflow $purchaseOrderWorkflow,
        Request $request
    ): array {
        $freshOrder = $purchaseOrder->fresh([
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

        $lineTotals = $lifecycleService->computeLineTotals($freshOrder);
        $tenantCurrency = strtoupper(
            (string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD'))
        );
        $workflow = $purchaseOrderWorkflow->responsePayload($freshOrder, $request->user());

        return [
            'id' => $shortClosureId,
            'purchase_order' => [
                'id' => $freshOrder->id,
                'status' => $freshOrder->status,
                'persisted_status' => $freshOrder->status,
                'workflow_status' => $freshOrder->workflowStatus(),
                'is_cancelled' => $freshOrder->isCancelled(),
                'is_editable' => $freshOrder->isWorkflowEditable(),
                'is_back_ordered' => $freshOrder->back_ordered_at !== null,
                'has_receipts' => $freshOrder->receipts->isNotEmpty(),
            ],
            'lines' => $freshOrder->lines
                ->map(fn (PurchaseOrderLine $line): array => $this->linePayload(
                    $line,
                    $tenantCurrency,
                    $lineTotals[$line->id] ?? []
                ))
                ->values()
                ->all(),
            'shortClosures' => $this->shortClosureHistoryPayload($freshOrder),
            'workflow' => $workflow,
            'workflowProgressSteps' => $purchaseOrderWorkflow->progressSteps($freshOrder),
            'can_receive' => $freshOrder->isReceivingStage()
                && collect($lineTotals)->contains(fn (array $totals): bool => bccomp(
                    $totals['balance'],
                    '0',
                    self::SCALE
                ) === 1),
        ];
    }

    /**
     * Build line state for the short-close response.
     *
     * @return array<string, mixed>
     */
    private function linePayload(PurchaseOrderLine $line, string $tenantCurrency, array $lineTotals = []): array
    {
        $option = $line->purchaseOption;
        $packCount = bcadd((string) $line->pack_count, '0', self::SCALE);
        $packQuantity = $option ? bcadd((string) $option->pack_quantity, '0', self::SCALE) : null;
        $packPrecision = (int) ($option?->packUom?->display_precision ?? 1);
        $packQuantityDisplay = $packQuantity !== null
            ? QuantityFormatter::format($packQuantity, $packPrecision)
            : null;
        $packUom = $option?->packUom?->symbol ?: $option?->packUom?->name;
        $receivedSum = $lineTotals['received_sum'] ?? '0.000000';
        $shortClosedSum = $lineTotals['short_closed_sum'] ?? '0.000000';
        $balance = $lineTotals['balance'] ?? $packCount;

        return [
            'id' => $line->id,
            'item_id' => $line->item_id,
            'item_name' => $line->item?->name,
            'item_purchase_option_id' => $line->item_purchase_option_id,
            'pack_count' => (int) $line->pack_count,
            'pack_count_display' => QuantityFormatter::format($packCount, $packPrecision),
            'unit_price_cents' => $line->unit_price_cents,
            'line_subtotal_cents' => $line->line_subtotal_cents,
            'line_tax_cents' => $this->taxCentsForLine(
                (int) $line->line_subtotal_cents,
                (int) $line->line_tax_rate_bps
            ),
            'tax_percent' => $this->basisPointsToTaxPercent((int) $line->line_tax_rate_bps),
            'line_tax_rate_bps' => (int) $line->line_tax_rate_bps,
            'pack_quantity' => $packQuantity,
            'pack_quantity_display' => $packQuantityDisplay,
            'pack_precision' => $packPrecision,
            'pack_uom_symbol' => $option?->packUom?->symbol,
            'pack_uom_name' => $option?->packUom?->name,
            'unit_context' => $packQuantityDisplay && $packUom ? "{$packQuantityDisplay} {$packUom} pack" : 'Pack',
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
     * Calculate line-level tax cents from basis points.
     */
    private function taxCentsForLine(int $lineSubtotalCents, int $lineTaxRateBps): int
    {
        return intdiv(($lineSubtotalCents * $lineTaxRateBps) + 5000, 10000);
    }

    /**
     * Build short-closure history payloads for the short-close response.
     *
     * @return array<int, array<string, mixed>>
     */
    private function shortClosureHistoryPayload(PurchaseOrder $purchaseOrder): array
    {
        return $purchaseOrder->shortClosures
            ->sortByDesc('short_closed_at')
            ->map(function ($shortClosure): array {
                $total = '0.000000';

                foreach ($shortClosure->lines as $line) {
                    $total = bcadd($total, (string) $line->short_closed_quantity, self::SCALE);
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
}
