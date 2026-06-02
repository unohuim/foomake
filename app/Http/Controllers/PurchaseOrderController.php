<?php

namespace App\Http\Controllers;

use App\Actions\Workflows\CanViewAssignedWorkflowResourceAction;
use App\Models\ItemPurchaseOption;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Support\QuantityFormatter;
use App\Services\Purchasing\PurchaseOrderLifecycleService;
use App\Services\Workflows\WorkflowTransitionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
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

        $tenantCurrency = strtoupper(
            (string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD'))
        );
        $crudConfig = $this->purchaseOrdersCrudConfig();
        $purchaseOrders = $this->purchaseOrdersQuery('', 'created_at', 'desc')->get();

        return view('purchasing.orders.index', [
            'crudConfig' => $crudConfig,
            'payload' => [
                'orders' => $this->purchaseOrderIndexRows($purchaseOrders, $tenantCurrency, $lifecycleService),
                'storeUrl' => $crudConfig['endpoints']['create'],
                'csrfToken' => csrf_token(),
                'tenantCurrency' => $tenantCurrency,
            ],
        ]);
    }

    /**
     * Return the purchase orders list read model for the shared CRUD page module.
     */
    public function list(Request $request, PurchaseOrderLifecycleService $lifecycleService): JsonResponse
    {
        Gate::authorize('purchasing-purchase-orders-create');

        $crudConfig = $this->purchaseOrdersCrudConfig();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', Rule::in($crudConfig['sortable'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ]);

        $tenantCurrency = strtoupper(
            (string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD'))
        );
        $search = trim((string) ($validated['search'] ?? ''));
        $sortColumn = (string) ($validated['sort'] ?? 'created_at');
        $direction = (string) ($validated['direction'] ?? 'desc');
        $purchaseOrders = $this->purchaseOrdersQuery($search, $sortColumn, $direction)->get();

        return response()->json([
            'data' => $this->purchaseOrderIndexRows($purchaseOrders, $tenantCurrency, $lifecycleService),
            'meta' => [
                'search' => $search,
                'sort' => [
                    'column' => $sortColumn,
                    'direction' => $direction,
                ],
                'allowed_sort_columns' => $crudConfig['sortable'],
                'total' => $purchaseOrders->count(),
            ],
        ]);
    }

    /**
     * Store a new draft purchase order.
     */
    public function store(Request $request, WorkflowTransitionService $workflowTransitionService): JsonResponse
    {
        Gate::authorize('purchasing-purchase-orders-create');

        $validator = Validator::make($request->all(), [
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'order_date' => ['nullable', 'date'],
            'shipping_amount' => ['nullable', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'po_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
        $this->rejectLegacyMoneyInputs($validator, $request);

        $validated = $validator->validate();

        $orderDate = $validated['order_date'] ?? null;

        $purchaseOrder = PurchaseOrder::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'created_by_user_id' => $request->user()->id,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'order_date' => $orderDate ? Carbon::parse($orderDate)->toDateString() : null,
            'shipping_cents' => array_key_exists('shipping_amount', $validated)
                ? $this->amountToCents($validated['shipping_amount'])
                : null,
            'tax_cents' => 0,
            'po_number' => $validated['po_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'po_subtotal_cents' => 0,
            'po_grand_total_cents' => 0,
        ]);
        $purchaseOrder = $workflowTransitionService->initializePurchaseOrder($purchaseOrder);

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
        PurchaseOrderLifecycleService $lifecycleService,
        WorkflowTransitionService $workflowTransitionService
    ): View
    {
        abort_unless((int) $purchaseOrder->tenant_id === (int) $request->user()->tenant_id, 404);
        abort_unless(
            Gate::allows('purchasing-purchase-orders-create')
                || app(CanViewAssignedWorkflowResourceAction::class)->execute(
                    $request->user(),
                    $purchaseOrder,
                    'purchasing'
                ),
            403
        );

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
            'currentWorkflowStage',
            'lastCompletedWorkflowStage',
        ]);

        $tenantCurrency = strtoupper(
            (string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD'))
        );

        $suppliers = Supplier::query()
            ->orderBy('company_name')
            ->get(['id', 'company_name']);

        $purchaseOptions = ItemPurchaseOption::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('is_active', true)
            ->with(['supplier', 'item', 'packUom', 'currentPrice'])
            ->orderBy('id')
            ->get();

        $lineTotals = $lifecycleService->computeLineTotals($purchaseOrder);
        $canReceive = Gate::allows('purchasing-purchase-orders-receive');

        $payload = [
            'purchaseOrder' => $this->purchaseOrderPayload($purchaseOrder),
            'workflow' => $workflowTransitionService->purchaseOrderWorkflowPayload($purchaseOrder, $request->user()),
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
                    'supplier_name' => $option->supplier?->company_name,
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

        if (! $purchaseOrder->isWorkflowEditable()) {
            return $this->lockedPurchaseOrderResponse();
        }

        $validator = Validator::make($request->all(), [
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'order_date' => ['nullable', 'date'],
            'shipping_amount' => ['nullable', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'po_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
        $this->rejectLegacyMoneyInputs($validator, $request);

        $validated = $validator->validate();

        $payload = $request->all();
        $updateData = [];
        $tenantCurrency = strtoupper(
            (string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD'))
        );

        if (array_key_exists('supplier_id', $payload)) {
            $updateData['supplier_id'] = $validated['supplier_id'] ?? null;
        }

        if (array_key_exists('order_date', $payload)) {
            $orderDate = $validated['order_date'] ?? null;
            $updateData['order_date'] = $orderDate ? Carbon::parse($orderDate)->toDateString() : null;
        }

        if (array_key_exists('shipping_amount', $payload)) {
            $updateData['shipping_cents'] = array_key_exists('shipping_amount', $validated)
                ? $this->amountToCents($validated['shipping_amount'])
                : null;
        }

        if (array_key_exists('po_number', $payload)) {
            $updateData['po_number'] = $validated['po_number'] ?? null;
        }

        if (array_key_exists('notes', $payload)) {
            $updateData['notes'] = $validated['notes'] ?? null;
        }

        $updatedOrder = null;

        $updatedLines = [];

        DB::transaction(function () use ($purchaseOrder, $updateData, $tenantCurrency, &$updatedOrder, &$updatedLines) {
            $lockedOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if (! $lockedOrder->isWorkflowEditable()) {
                $updatedOrder = $lockedOrder;
                return;
            }

            $supplierChanged = array_key_exists('supplier_id', $updateData)
                && (int) ($lockedOrder->supplier_id ?? 0) !== (int) ($updateData['supplier_id'] ?? 0);

            if ($updateData !== []) {
                $lockedOrder->update($updateData);
            }

            if ($supplierChanged) {
                PurchaseOrderLine::query()
                    ->where('tenant_id', $lockedOrder->tenant_id)
                    ->where('purchase_order_id', $lockedOrder->id)
                    ->delete();
            }

            $this->recalculateTotals($lockedOrder);
            $updatedOrder = $lockedOrder->fresh(['supplier', 'lines.item', 'lines.purchaseOption.packUom']);
            $lineTotals = app(PurchaseOrderLifecycleService::class)->computeLineTotals($updatedOrder);
            $updatedLines = $updatedOrder->lines
                ->map(fn (PurchaseOrderLine $line): array => $this->linePayload(
                    $line,
                    $tenantCurrency,
                    $lineTotals[$line->id] ?? []
                ))
                ->values()
                ->all();
        });

        if (! $updatedOrder || ! $updatedOrder->isWorkflowEditable()) {
            return $this->lockedPurchaseOrderResponse();
        }

        $responsePayload = $this->purchaseOrderPayload($updatedOrder);

        return response()->json([
            'data' => array_merge($responsePayload, [
                'purchase_order' => $responsePayload,
                'lines' => $updatedLines,
            ]),
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
        $payload = [
            'id' => $purchaseOrder->id,
            'supplier_id' => $purchaseOrder->supplier_id,
            'supplier_name' => $purchaseOrder->supplier?->company_name,
            'order_date' => $purchaseOrder->order_date?->format('Y-m-d'),
            'shipping_cents' => $purchaseOrder->shipping_cents,
            'shipping_amount' => $this->formatCentsToAmount($purchaseOrder->shipping_cents),
            'tax_cents' => $purchaseOrder->tax_cents,
            'po_number' => $purchaseOrder->po_number,
            'notes' => $purchaseOrder->notes,
            'status' => $purchaseOrder->workflowStatus(),
            'persisted_status' => $purchaseOrder->status,
            'is_cancelled' => $purchaseOrder->workflow_cancelled_at !== null,
            'is_editable' => $purchaseOrder->isWorkflowEditable(),
            'is_back_ordered' => $purchaseOrder->back_ordered_at !== null,
            'has_receipts' => $purchaseOrder->receipts()->exists(),
            'po_subtotal_cents' => $purchaseOrder->po_subtotal_cents,
            'po_grand_total_cents' => $purchaseOrder->po_grand_total_cents,
        ];

        if (Schema::hasColumn('purchase_orders', 'current_workflow_stage_id')) {
            $payload['current_workflow_stage_id'] = $purchaseOrder->getAttribute('current_workflow_stage_id');
            $payload['last_completed_workflow_stage_id'] = $purchaseOrder->getAttribute('last_completed_workflow_stage_id');
        }

        return $payload;
    }

    /**
     * Build the shared CRUD config for the purchase orders index.
     *
     * @return array<string, mixed>
     */
    private function purchaseOrdersCrudConfig(): array
    {
        return [
            'resource' => 'purchase-orders',
            'endpoints' => [
                'list' => route('purchasing.orders.list'),
                'create' => route('purchasing.orders.store'),
                'update' => '',
                'delete' => '',
            ],
            'detailUrlTemplate' => url('/purchasing/orders/{id}'),
            'columns' => ['order', 'supplier_name', 'status', 'po_grand_total_cents', 'lines_count'],
            'headers' => [
                'order' => 'Order',
                'supplier_name' => 'Supplier',
                'status' => 'Status',
                'po_grand_total_cents' => 'Total',
                'lines_count' => 'Lines',
            ],
            'sortable' => ['order_date', 'po_grand_total_cents', 'lines_count'],
            'labels' => [
                'searchPlaceholder' => 'Search purchase orders',
                'createTitle' => 'Create Purchase Order',
                'createAriaLabel' => 'Create Purchase Order',
                'emptyState' => 'No purchase orders found.',
                'actionsAriaLabel' => 'Purchase order actions',
            ],
            'permissions' => [
                'showExport' => false,
                'showImport' => false,
                'showCreate' => Gate::allows('purchasing-purchase-orders-create'),
            ],
            'rowDisplay' => [
                'columns' => [
                    'order' => [
                        'kind' => 'stacked-text',
                        'urlExpression' => 'record.show_url',
                        'subtitleExpression' => "record.order_date || 'No order date'",
                    ],
                    'supplier_name' => ['kind' => 'text'],
                    'status' => ['kind' => 'text'],
                    'po_grand_total_cents' => ['kind' => 'text'],
                    'lines_count' => ['kind' => 'text'],
                ],
            ],
            'mobileCard' => [
                'titleExpression' => "record.order || 'Draft PO'",
                'subtitleExpression' => "record.supplier_name || 'Supplier not set'",
                'bodyExpression' => 'purchaseOrderMobileSummary(record)',
            ],
            'actions' => [],
        ];
    }

    /**
     * Build the purchase orders index query for configured CRUD consumers.
     */
    private function purchaseOrdersQuery(string $search, string $sortColumn, string $direction): Builder
    {
        $sortableColumns = [
            'created_at' => 'purchase_orders.created_at',
            'order_date' => 'purchase_orders.order_date',
            'po_grand_total_cents' => 'purchase_orders.po_grand_total_cents',
            'lines_count' => 'lines_count',
        ];

        $query = PurchaseOrder::query()
            ->with('supplier')
            ->with('lines')
            ->with('lines.item')
            ->with('lines.purchaseOption.packUom')
            ->with('currentWorkflowStage')
            ->with('lastCompletedWorkflowStage')
            ->withCount('lines');

        if ($search !== '') {
            $query->where(function (Builder $nested) use ($search): void {
                $nested->where('po_number', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%')
                    ->orWhere('order_date', 'like', '%' . $search . '%')
                    ->orWhereHas('supplier', function (Builder $supplierQuery) use ($search): void {
                        $supplierQuery->where('company_name', 'like', '%' . $search . '%');
                    });
            });
        }

        $orderColumn = $sortableColumns[$sortColumn] ?? 'purchase_orders.created_at';

        return $query
            ->orderBy($orderColumn, $direction)
            ->orderByDesc('purchase_orders.created_at')
            ->orderByDesc('purchase_orders.id');
    }

    /**
     * Build purchase order row payloads for the configured CRUD renderer.
     *
     * @return array<int, array<string, mixed>>
     */
    private function purchaseOrderIndexRows(
        iterable $purchaseOrders,
        string $tenantCurrency,
        PurchaseOrderLifecycleService $lifecycleService
    ): array {
        $rows = [];

        foreach ($purchaseOrders as $purchaseOrder) {
            $lineTotals = $lifecycleService->computeLineTotals($purchaseOrder);
            $linesPayload = $purchaseOrder->lines->map(function (PurchaseOrderLine $line) use ($lineTotals) {
                $packCount = bcadd((string) $line->pack_count, '0', 6);
                $totals = $lineTotals[$line->id] ?? [];
                $option = $line->purchaseOption;
                $packPrecision = (int) ($option?->packUom?->display_precision ?? 1);
                $packQuantity = $option ? bcadd((string) $option->pack_quantity, '0', 6) : null;
                $packQuantityDisplay = $packQuantity !== null
                    ? QuantityFormatter::format($packQuantity, $packPrecision)
                    : null;
                $packUom = $option?->packUom?->symbol ?: $option?->packUom?->name;

                return [
                    'id' => $line->id,
                    'item_name' => $line->item?->name,
                    'pack_count' => $packCount,
                    'pack_precision' => $packPrecision,
                    'pack_count_display' => QuantityFormatter::format($packCount, $packPrecision),
                    'unit_context' => $packQuantityDisplay && $packUom ? "{$packQuantityDisplay} {$packUom} pack" : 'Pack',
                    'received_sum' => $totals['received_sum'] ?? '0.000000',
                    'received_sum_display' => QuantityFormatter::format($totals['received_sum'] ?? '0.000000', $packPrecision),
                    'short_closed_sum' => $totals['short_closed_sum'] ?? '0.000000',
                    'short_closed_sum_display' => QuantityFormatter::format($totals['short_closed_sum'] ?? '0.000000', $packPrecision),
                    'remaining_balance' => $totals['balance'] ?? $packCount,
                    'remaining_balance_display' => QuantityFormatter::format($totals['balance'] ?? $packCount, $packPrecision),
                ];
            })->values()->all();

            $rows[] = [
                'id' => $purchaseOrder->id,
                'order' => $purchaseOrder->po_number ? 'PO #' . $purchaseOrder->po_number : 'Draft PO',
                'supplier_name' => $purchaseOrder->supplier?->company_name ?: 'Supplier not set',
                'status' => $purchaseOrder->workflowStatus(),
                'persisted_status' => $purchaseOrder->status,
                'is_cancelled' => $purchaseOrder->workflow_cancelled_at !== null,
                'is_back_ordered' => $purchaseOrder->back_ordered_at !== null,
                'order_date' => $purchaseOrder->order_date?->format('Y-m-d'),
                'po_number' => $purchaseOrder->po_number,
                'po_subtotal_cents' => $purchaseOrder->po_subtotal_cents,
                'po_grand_total_cents' => $purchaseOrder->po_grand_total_cents,
                'po_grand_total_display' => $tenantCurrency . ' ' . number_format(((int) $purchaseOrder->po_grand_total_cents) / 100, 2),
                'lines_count' => $purchaseOrder->lines_count ?? $purchaseOrder->lines->count(),
                'lines' => $linesPayload,
                'show_url' => route('purchasing.orders.show', $purchaseOrder),
            ];
        }

        return $rows;
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
            'pack_count' => (int) $line->pack_count,
            'pack_count_display' => QuantityFormatter::format($packCount, $packPrecision),
            'unit_price_cents' => $line->unit_price_cents,
            'line_subtotal_cents' => $line->line_subtotal_cents,
            'line_tax_cents' => $this->taxCentsForLine((int) $line->line_subtotal_cents, (int) $line->line_tax_rate_bps),
            'tax_percent' => $this->basisPointsToTaxPercent((int) $line->line_tax_rate_bps),
            'line_tax_rate_bps' => (int) $line->line_tax_rate_bps,
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
     * Convert a dollars/cents amount string to integer cents.
     */
    private function amountToCents(?string $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $parts = explode('.', $amount, 2);
        $dollars = (int) $parts[0];
        $cents = (int) str_pad($parts[1] ?? '0', 2, '0');

        return ($dollars * 100) + $cents;
    }

    /**
     * Format integer cents as a dollars/cents input value.
     */
    private function formatCentsToAmount(?int $cents): ?string
    {
        if ($cents === null) {
            return null;
        }

        $dollars = intdiv($cents, 100);
        $minor = $cents % 100;

        return $dollars . '.' . str_pad((string) $minor, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate line-level tax cents from basis points.
     */
    private function taxCentsForLine(int $lineSubtotalCents, int $lineTaxRateBps): int
    {
        return intdiv(($lineSubtotalCents * $lineTaxRateBps) + 5000, 10000);
    }

    /**
     * Add new-contract validation errors for removed money inputs.
     */
    private function rejectLegacyMoneyInputs(\Illuminate\Contracts\Validation\Validator $validator, Request $request): void
    {
        $validator->after(function (\Illuminate\Contracts\Validation\Validator $validator) use ($request): void {
            if ($request->exists('shipping_cents')) {
                $validator->errors()->add('shipping_amount', 'Shipping must be entered as dollars and cents.');
            }

            if ($request->exists('tax_cents')) {
                $validator->errors()->add('tax_cents', 'Tax is calculated from purchase order lines.');
            }
        });
    }

    /**
     * Build a JSON response for locked purchase order edits.
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
     * Convert basis points into displayable tax percentage text.
     */
    private function basisPointsToTaxPercent(int $basisPoints): string
    {
        $tenths = intdiv($basisPoints + 5, 10);
        $whole = intdiv($tenths, 10);
        $fraction = $tenths % 10;

        return $whole . '.' . $fraction;
    }
}
