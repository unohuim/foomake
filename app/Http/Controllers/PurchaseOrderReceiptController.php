<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\Purchasing\PurchaseOrderLifecycleService;
use App\Services\Workflows\PurchaseOrderWorkflow;
use App\Support\QuantityFormatter;
use App\Support\Workflows\WorkflowAssignmentPermissions;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderReceiptController extends Controller
{
    private const SCALE = 6;

    /**
     * Store a new purchase order receipt event.
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
            'received_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];

        if ($hasLinesArray) {
            $rules['lines'] = ['required', 'array', 'min:1'];
            $rules['lines.*.purchase_order_line_id'] = ['required', 'integer'];
            $rules['lines.*.received_quantity'] = ['nullable', 'numeric'];
        } else {
            $rules['purchase_order_line_id'] = ['required', 'integer'];
            $rules['received_quantity'] = ['required', 'numeric'];
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request, $purchaseOrder, $hasLinesArray, $lifecycleService) {
            $lineInputs = $hasLinesArray ? (array) $request->input('lines') : [[
                'purchase_order_line_id' => $request->input('purchase_order_line_id'),
                'received_quantity' => $request->input('received_quantity'),
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
            $positiveLineCount = 0;
            $requestedByLine = [];

            foreach ($lineInputs as $index => $lineInput) {
                $prefix = $hasLinesArray ? "lines.{$index}." : '';
                $lineId = (int) ($lineInput['purchase_order_line_id'] ?? 0);
                $quantity = (string) ($lineInput['received_quantity'] ?? '');

                if ($hasLinesArray && $this->isBlankOrZeroQuantity($quantity)) {
                    continue;
                }

                if (! $lineId || ! $orderLines->has($lineId)) {
                    $validator->errors()->add("{$prefix}purchase_order_line_id", 'Line is invalid for this purchase order.');
                    continue;
                }

                if (! is_numeric($quantity)) {
                    continue;
                }

                if (! $this->isWholeQuantity($quantity)) {
                    $validator->errors()->add("{$prefix}received_quantity", 'Quantity must be a whole number.');
                    continue;
                }

                if (bccomp($quantity, '0', self::SCALE) <= 0) {
                    $validator->errors()->add("{$prefix}received_quantity", 'Quantity must be greater than zero.');
                    continue;
                }

                $positiveLineCount++;
                $requestedByLine[$lineId] = bcadd(
                    $requestedByLine[$lineId] ?? '0.000000',
                    $quantity,
                    self::SCALE
                );

                $balance = $lineTotals[$lineId]['balance'] ?? '0.000000';

                if (bccomp($quantity, $balance, self::SCALE) === 1) {
                    $validator->errors()->add("{$prefix}received_quantity", 'Quantity exceeds remaining balance.');
                }
            }

            foreach ($requestedByLine as $lineId => $requestedQuantity) {
                $balance = $lineTotals[$lineId]['balance'] ?? '0.000000';

                if (bccomp($requestedQuantity, $balance, self::SCALE) === 1) {
                    $validator->errors()->add('lines', 'Total received quantity exceeds remaining balance.');
                    break;
                }
            }

            if ($hasLinesArray && $positiveLineCount === 0) {
                $validator->errors()->add('lines', 'At least one positive received quantity is required.');
            }
        });

        $validated = $validator->validate();

        $lineInputs = $hasLinesArray ? (array) $request->input('lines') : [[
            'purchase_order_line_id' => $validated['purchase_order_line_id'] ?? null,
            'received_quantity' => $validated['received_quantity'] ?? null,
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
            $quantity = (string) ($lineInput['received_quantity'] ?? '');

            if (! $line) {
                continue;
            }

            if ($hasLinesArray && $this->isBlankOrZeroQuantity($quantity)) {
                continue;
            }

            $lineItems[] = [
                'line' => $line,
                'quantity' => $quantity,
            ];
        }

        try {
            $receipt = $lifecycleService->createReceipt(
                $purchaseOrder,
                $request->user(),
                $lineItems,
                $validated['received_at'] ?? null,
                $validated['reference'] ?? null,
                $validated['notes'] ?? null
            );
        } catch (DomainException $exception) {
            $errorKey = str_contains(strtolower($exception->getMessage()), 'conversion')
                ? 'conversion'
                : 'status';

            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    $errorKey => [$exception->getMessage()],
                ],
            ], 422);
        }

        return response()->json([
            'data' => $this->receiptResponsePayload(
                $receipt->id,
                $purchaseOrder,
                $lifecycleService,
                $purchaseOrderWorkflow,
                $request
            ),
        ], 201);
    }

    /**
     * Determine whether a line quantity should be ignored for multi-line receipts.
     */
    private function isBlankOrZeroQuantity(string $quantity): bool
    {
        $trimmed = trim($quantity);

        if ($trimmed === '') {
            return true;
        }

        return preg_match('/^-?\d+(\.\d+)?$/', $trimmed) === 1
            && bccomp($trimmed, '0', self::SCALE) === 0;
    }

    /**
     * Determine whether the submitted pack quantity is whole-number compatible.
     */
    private function isWholeQuantity(string $quantity): bool
    {
        $trimmed = trim($quantity);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $trimmed)) {
            return false;
        }

        if (! str_contains($trimmed, '.')) {
            return true;
        }

        [, $fraction] = explode('.', $trimmed, 2);

        return trim($fraction, '0') === '';
    }

    /**
     * Build the receipt success payload consumed by the PO detail page.
     *
     * @return array<string, mixed>
     */
    private function receiptResponsePayload(
        int $receiptId,
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
        ]);

        $lineTotals = $lifecycleService->computeLineTotals($freshOrder);
        $tenantCurrency = strtoupper(
            (string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD'))
        );
        $workflow = $purchaseOrderWorkflow->responsePayload($freshOrder, $request->user());

        return [
            'id' => $receiptId,
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
            'receipts' => $this->receiptHistoryPayload($freshOrder),
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
     * Build line state for the receipt response.
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
     * Calculate line-level tax cents from basis points.
     */
    private function taxCentsForLine(int $lineSubtotalCents, int $lineTaxRateBps): int
    {
        return intdiv(($lineSubtotalCents * $lineTaxRateBps) + 5000, 10000);
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
     * Build receipt history payloads for the receipt response.
     *
     * @return array<int, array<string, mixed>>
     */
    private function receiptHistoryPayload(PurchaseOrder $purchaseOrder): array
    {
        return $purchaseOrder->receipts
            ->sortByDesc('received_at')
            ->map(function ($receipt): array {
                $total = '0.000000';

                foreach ($receipt->lines as $line) {
                    $total = bcadd($total, (string) $line->received_quantity, self::SCALE);
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
}
