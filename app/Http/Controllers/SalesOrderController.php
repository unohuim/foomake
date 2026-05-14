<?php

namespace App\Http\Controllers;

use App\Actions\Workflows\ResolveSalesWorkflowStageAction;
use App\Http\Requests\Sales\ImportExternalSalesOrdersRequest;
use App\Http\Requests\Sales\ListSalesOrdersRequest;
use App\Http\Requests\Sales\PreviewExternalSalesOrderImportRequest;
use App\Http\Requests\Sales\StoreSalesOrderRequest;
use App\Http\Requests\Sales\UpdateSalesOrderRequest;
use App\Integrations\WooCommerce\WooCommerceException;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\ExternalCustomerMapping;
use App\Models\ExternalProductSourceConnection;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Task;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Services\WooCommerceOrderPreviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Handle sales order CRUD, detail, import, and export flows.
 */
class SalesOrderController extends Controller
{
    private const SCALE = 6;

    /**
     * Display the sales orders index.
     */
    public function index(): View
    {
        Gate::authorize('sales-sales-orders-manage');

        $orders = SalesOrder::query()
            ->with(['customer', 'contact'])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();
        $customers = Customer::query()
            ->with('contacts')
            ->orderBy('name')
            ->get();
        $crudConfig = $this->ordersCrudConfig();
        $importConfig = $this->ordersImportConfig();

        $payload = [
            'orders' => $orders->map(fn (SalesOrder $order): array => $this->orderListData($order))->values()->all(),
            'customers' => $customers->map(fn (Customer $customer): array => $this->customerOptionData($customer))->values()->all(),
            'storeUrl' => route('sales.orders.store'),
            'updateUrlBase' => url('/sales/orders'),
            'deleteUrlBase' => url('/sales/orders'),
            'navigationStateUrl' => route('navigation.state'),
            'csrfToken' => csrf_token(),
            'statuses' => SalesOrder::statuses(),
        ];

        return view('sales.orders.index', [
            'crudConfig' => $crudConfig,
            'importConfig' => $importConfig,
            'payload' => $payload,
        ]);
    }

    /**
     * Display the sales order detail page.
     */
    public function show(SalesOrder $salesOrder): View
    {
        Gate::authorize('sales-sales-orders-manage');

        $salesOrder->load(['customer.contacts', 'contact', 'lines.item']);
        $customers = Customer::query()
            ->with('contacts')
            ->orderBy('name')
            ->get();
        $sellableItems = Item::query()
            ->where('is_sellable', true)
            ->orderBy('name')
            ->get();

        $payload = [
            'order' => $this->orderData($salesOrder),
            'customers' => $customers->map(fn (Customer $customer): array => $this->customerOptionData($customer))->values()->all(),
            'sellableItems' => $sellableItems->map(fn (Item $item): array => $this->sellableItemData($item))->values()->all(),
            'updateUrl' => route('sales.orders.update', $salesOrder),
            'deleteUrl' => route('sales.orders.destroy', $salesOrder),
            'lineStoreUrlBase' => url('/sales/orders'),
            'indexUrl' => route('sales.orders.index'),
            'csrfToken' => csrf_token(),
        ];

        return view('sales.orders.show', [
            'salesOrder' => $salesOrder,
            'payload' => $payload,
        ]);
    }

    /**
     * Return the Sales Orders list read model for the page module.
     */
    public function list(ListSalesOrdersRequest $request): JsonResponse
    {
        Gate::authorize('sales-sales-orders-manage');

        $validated = $request->validated();
        $crudConfig = $this->ordersCrudConfig();
        $search = trim((string) ($validated['search'] ?? ''));
        $sortColumn = (string) ($validated['sort'] ?? 'date');
        $direction = (string) ($validated['direction'] ?? 'desc');
        $orders = $this->ordersQuery($search, $sortColumn, $direction)
            ->with(['customer', 'contact'])
            ->get();

        return response()->json([
            'data' => $orders
                ->map(fn (SalesOrder $order): array => $this->orderListData($order))
                ->values()
                ->all(),
            'meta' => [
                'search' => $search,
                'sort' => [
                    'column' => $sortColumn,
                    'direction' => $direction,
                ],
                'allowed_sort_columns' => $crudConfig['sortable'],
                'total' => $orders->count(),
            ],
        ]);
    }

    /**
     * Download a CSV export for sales orders.
     */
    public function export(ListSalesOrdersRequest $request): StreamedResponse
    {
        Gate::authorize('sales-sales-orders-manage');

        $validated = $request->validated();
        $scope = (string) ($validated['scope'] ?? 'current');
        $search = $scope === 'all' ? '' : trim((string) ($validated['search'] ?? ''));
        $sortColumn = $scope === 'all' ? 'date' : (string) ($validated['sort'] ?? 'date');
        $direction = $scope === 'all' ? 'desc' : (string) ($validated['direction'] ?? 'desc');
        $orders = $this->ordersQuery($search, $sortColumn, $direction)
            ->with(['customer', 'contact', 'lines.item'])
            ->get();

        return response()->streamDownload(function () use ($orders): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $this->orderExportHeaders());

            foreach ($orders as $order) {
                foreach ($this->orderExportRows($order) as $row) {
                    fputcsv($handle, $row);
                }
            }

            fclose($handle);
        }, 'sales-orders-export.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=sales-orders-export.csv',
        ]);
    }

    /**
     * Return WooCommerce-backed preview rows for the selected source.
     */
    public function previewImport(
        PreviewExternalSalesOrderImportRequest $request,
        WooCommerceOrderPreviewService $previewService
    ): JsonResponse {
        Gate::authorize('sales-sales-orders-manage');

        $source = (string) $request->validated('source');
        $tenantId = (int) $request->user()->tenant_id;
        $submittedRows = $request->validated('rows', []);

        if (is_array($submittedRows) && $submittedRows !== []) {
            $statusError = $this->invalidExternalStatusResponse($submittedRows);

            if ($statusError !== null) {
                return $statusError;
            }

            return response()->json([
                'data' => [
                    'source' => $source,
                    'is_connected' => true,
                    'rows' => $this->annotateDuplicatePreviewRows($tenantId, $submittedRows, $source),
                ],
            ]);
        }

        $connection = $this->connectedSourceForTenant($tenantId, $source);

        if (! $connection) {
            return $this->notConnectedResponse();
        }

        try {
            $rows = match ($source) {
                ExternalProductSourceConnection::SOURCE_WOOCOMMERCE => $previewService->previewRows($connection),
                default => throw new WooCommerceException('The selected source is not supported.'),
            };
        } catch (WooCommerceException $exception) {
            return response()->json([
                'message' => 'The external sales order preview could not be loaded.',
                'errors' => [
                    'source' => [$exception->getMessage()],
                ],
            ], 422);
        }

        $statusError = $this->invalidExternalStatusResponse($rows);

        if ($statusError !== null) {
            return $statusError;
        }

        return response()->json([
            'data' => [
                'source' => $source,
                'is_connected' => true,
                'rows' => $this->annotateDuplicatePreviewRows($tenantId, $rows, $source),
            ],
        ]);
    }

    /**
     * Import selected preview rows as normal sales orders.
     */
    public function storeImport(ImportExternalSalesOrdersRequest $request): JsonResponse
    {
        Gate::authorize('sales-sales-orders-manage');

        $rows = $request->validated('rows');
        $statusError = $this->invalidExternalStatusResponse($rows);

        if ($statusError !== null) {
            return $statusError;
        }

        $tenantId = (int) $request->user()->tenant_id;
        $source = (string) $request->validated('source');
        $createdCount = 0;

        /** @var Collection<int, SalesOrder> $imported */
        $imported = collect();

        try {
            DB::transaction(function () use ($tenantId, $source, $rows, &$createdCount, &$imported): void {
                $imported = collect($rows)->map(function (array $row) use ($tenantId, $source, &$createdCount): SalesOrder {
                    [$order, $wasCreated] = $this->createOrRefreshImportedOrder($tenantId, $source, $row);

                    if ($wasCreated) {
                        $createdCount++;
                    }

                    return $order;
                });
            });
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'Unable to import orders because the database write failed.',
                'errors' => [
                    'import' => [$exception->getMessage()],
                ],
            ], 500);
        }

        return response()->json([
            'data' => [
                'imported_count' => $imported->count(),
                'created_count' => $createdCount,
                'imported' => $imported
                    ->map(fn (SalesOrder $order): array => $this->orderData($order->fresh(['customer', 'contact', 'lines.item'])))
                    ->values()
                    ->all(),
            ],
        ], $createdCount > 0 ? 201 : 200);
    }

    /**
     * Store a new sales order.
     */
    public function store(StoreSalesOrderRequest $request): JsonResponse
    {
        Gate::authorize('sales-sales-orders-manage');

        $customer = Customer::query()
            ->with('contacts')
            ->findOrFail((int) $request->validated('customer_id'));

        $order = SalesOrder::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'customer_id' => $customer->id,
            'contact_id' => $this->resolveContactId($request, $customer, null),
            'order_date' => $this->normalizedOrderDate($request->validated('order_date')),
            'status' => SalesOrder::STATUS_DRAFT,
        ]);

        $order->load(['customer', 'contact', 'lines.item']);

        return response()->json([
            'data' => $this->orderData($order),
        ], 201);
    }

    /**
     * Update an existing sales order.
     */
    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesOrder): JsonResponse
    {
        Gate::authorize('sales-sales-orders-manage');

        if (! $salesOrder->isEditable()) {
            return $this->editableOrderResponse();
        }

        $customer = Customer::query()
            ->with('contacts')
            ->findOrFail((int) $request->validated('customer_id'));

        $salesOrder->update([
            'customer_id' => $customer->id,
            'contact_id' => $this->resolveContactId($request, $customer, $salesOrder),
            'order_date' => $this->normalizedOrderDate($request->validated('order_date')),
        ]);

        $salesOrder->load(['customer', 'contact', 'lines.item']);

        return response()->json([
            'data' => $this->orderData($salesOrder),
        ]);
    }

    /**
     * Delete a sales order.
     */
    public function destroy(SalesOrder $salesOrder): JsonResponse
    {
        Gate::authorize('sales-sales-orders-manage');

        $salesOrder->delete();

        return response()->json([
            'message' => 'Deleted.',
        ]);
    }

    /**
     * Build the sales order response payload.
     *
     * @return array<string, int|string|bool|null|array<int, array<string, int|string|null>>|list<string>>
     */
    public function orderData(SalesOrder $order): array
    {
        $contactName = null;
        $orderTotalCents = '0.000000';

        if ($order->contact) {
            $contactName = $order->contact->full_name;
        }

        $lines = $order->lines->map(function (SalesOrderLine $line) use (&$orderTotalCents): array {
            $orderTotalCents = bcadd($orderTotalCents, (string) $line->line_total_cents, self::SCALE);

            return $this->lineData($line);
        })->values()->all();

        return [
            'id' => $order->id,
            'date' => $this->formattedDate($order->order_date),
            'customer_id' => $order->customer_id,
            'customer_name' => $order->customer?->name,
            'contact_id' => $order->contact_id,
            'contact_name' => $contactName,
            'city' => $order->customer?->city,
            'status' => $order->status,
            'can_edit' => $order->isEditable(),
            'can_manage_lines' => $order->allowsLineMutations(),
            'available_status_transitions' => $order->availableTransitions(),
            'status_update_url' => route('sales.orders.status.update', $order),
            'show_url' => route('sales.orders.show', $order),
            'lines' => $lines,
            'line_count' => count($lines),
            'order_total_cents' => $orderTotalCents,
            'order_total_amount' => bcdiv($orderTotalCents, '100', self::SCALE),
            'current_stage_tasks' => $this->currentStageTasksData($order),
            'external_source' => $order->external_source,
            'external_id' => $order->external_id,
            'external_status' => $order->external_status,
        ];
    }

    /**
     * Build the sales order list payload.
     *
     * @return array<string, int|string|null>
     */
    public function orderListData(SalesOrder $order): array
    {
        $contactName = null;

        if ($order->contact) {
            $contactName = $order->contact->full_name;
        }

        return [
            'id' => $order->id,
            'date' => $this->formattedDate($order->order_date),
            'customer_id' => $order->customer_id,
            'contact_id' => $order->contact_id,
            'customer_name' => $order->customer?->name,
            'contact_name' => $contactName,
            'city' => $order->customer?->city,
            'status' => $order->status,
            'can_edit' => $order->isEditable(),
            'available_status_transitions' => $order->availableTransitions(),
            'show_url' => route('sales.orders.show', $order),
            'external_source' => $order->external_source,
            'external_id' => $order->external_id,
            'external_status' => $order->external_status,
        ];
    }

    /**
     * Build customer options for the sales order forms.
     *
     * @return array<string, int|string|null|array<int, array<string, int|string|bool|null>>>
     */
    public function customerOptionData(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'primary_contact_id' => $customer->contacts->firstWhere('is_primary', true)?->id,
            'contacts' => $customer->contacts
                ->map(fn (CustomerContact $contact): array => [
                    'id' => $contact->id,
                    'customer_id' => $contact->customer_id,
                    'full_name' => $contact->full_name,
                    'is_primary' => $contact->is_primary,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Build sellable item options for sales order lines.
     *
     * @return array<string, int|string|null>
     */
    public function sellableItemData(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'default_price_cents' => $item->default_price_cents,
            'default_price_currency_code' => $item->default_price_currency_code,
        ];
    }

    /**
     * Build the sales order line response payload.
     *
     * @return array<string, int|string|null>
     */
    public function lineData(SalesOrderLine $line): array
    {
        $lineTotalCents = (string) $line->line_total_cents;

        return [
            'id' => $line->id,
            'item_id' => $line->item_id,
            'item_name' => $line->item?->name,
            'quantity' => (string) $line->quantity,
            'unit_price_cents' => $line->unit_price_cents,
            'unit_price_currency_code' => $line->unit_price_currency_code,
            'unit_price_amount' => bcdiv((string) $line->unit_price_cents, '100', 2),
            'line_total_cents' => $lineTotalCents,
            'line_total_amount' => bcdiv($lineTotalCents, '100', self::SCALE),
        ];
    }

    /**
     * Resolve the contact to persist for the sales order.
     */
    private function resolveContactId(
        StoreSalesOrderRequest|UpdateSalesOrderRequest $request,
        Customer $customer,
        ?SalesOrder $existingOrder
    ): ?int {
        $payload = $request->all();
        $contactKeySubmitted = array_key_exists('contact_id', $payload);
        $validatedContactId = $request->validated('contact_id');
        $customerChanged = $existingOrder === null || $existingOrder->customer_id !== $customer->id;
        $primaryContactId = $customer->contacts->firstWhere('is_primary', true)?->id;

        if ($validatedContactId !== null) {
            return (int) $validatedContactId;
        }

        if ($existingOrder === null) {
            return $primaryContactId;
        }

        if ($customerChanged) {
            return $primaryContactId;
        }

        if ($contactKeySubmitted) {
            return null;
        }

        return $existingOrder->contact_id;
    }

    /**
     * Return the shared editable-status mutation response.
     */
    private function editableOrderResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Only draft or open sales orders can be edited.',
            'errors' => [
                'customer_id' => [],
                'contact_id' => [],
                'order_date' => [],
            ],
        ], 422);
    }

    /**
     * Build the current-stage workflow tasks payload.
     *
     * @return array<int, array<string, int|string|bool|null>>
     */
    private function currentStageTasksData(SalesOrder $order): array
    {
        $stage = app(ResolveSalesWorkflowStageAction::class)->currentStageForStatus($order);

        if (! $stage) {
            return [];
        }

        $viewerUserId = auth()->id();

        return Task::query()
            ->with(['assignedTo', 'completedBy'])
            ->where('workflow_domain_id', $stage->workflow_domain_id)
            ->where('domain_record_id', $order->id)
            ->where('workflow_stage_id', $stage->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Task $task): array => $this->taskData($task, $viewerUserId))
            ->values()
            ->all();
    }

    /**
     * Build workflow task payload data.
     *
     * @return array<string, int|string|bool|null>
     */
    private function taskData(Task $task, ?int $viewerUserId): array
    {
        return [
            'id' => $task->id,
            'workflow_stage_id' => $task->workflow_stage_id,
            'workflow_task_template_id' => $task->workflow_task_template_id,
            'assigned_to_user_id' => $task->assigned_to_user_id,
            'assigned_to_user_name' => $task->assignedTo?->name,
            'title' => $task->title,
            'description' => $task->description,
            'sort_order' => $task->sort_order,
            'status' => $task->status,
            'is_completed' => $task->isCompleted(),
            'can_complete' => ! $task->isCompleted()
                && $viewerUserId !== null
                && (int) $task->assigned_to_user_id === (int) $viewerUserId,
            'completed_at' => $task->completed_at?->toISOString(),
            'completed_by_user_id' => $task->completed_by_user_id,
            'completed_by_user_name' => $task->completedBy?->name,
            'complete_url' => route('tasks.complete', $task),
        ];
    }

    /**
     * Build the tenant-scoped sales orders query used by list and export.
     */
    private function ordersQuery(string $search, string $sortColumn, string $direction): Builder
    {
        $query = SalesOrder::query()
            ->with('customer')
            ->leftJoin('customers', function ($join): void {
                $join->on('customers.id', '=', 'sales_orders.customer_id')
                    ->on('customers.tenant_id', '=', 'sales_orders.tenant_id');
            })
            ->select('sales_orders.*');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('customers.name', 'like', '%' . $search . '%')
                    ->orWhere('customers.city', 'like', '%' . $search . '%')
                    ->orWhere('sales_orders.status', 'like', '%' . $search . '%')
                    ->orWhere('sales_orders.external_id', 'like', '%' . $search . '%')
                    ->orWhere('sales_orders.id', 'like', '%' . $search . '%')
                    ->orWhere('sales_orders.order_date', 'like', '%' . $search . '%');
            });
        }

        match ($sortColumn) {
            'id' => $query->orderBy('sales_orders.id', $direction),
            'customer_name' => $query->orderBy('customers.name', $direction)->orderByDesc('sales_orders.id'),
            'city' => $query->orderBy('customers.city', $direction)->orderByDesc('sales_orders.id'),
            'status' => $query->orderBy('sales_orders.status', $direction)->orderByDesc('sales_orders.id'),
            default => $query->orderBy('sales_orders.order_date', $direction)->orderByDesc('sales_orders.id'),
        };

        return $query;
    }

    /**
     * Return the CSV header row for order exports.
     *
     * @return array<int, string>
     */
    private function orderExportHeaders(): array
    {
        return [
            'external_source',
            'order_external_id',
            'order_date',
            'customer_name',
            'contact_name',
            'city',
            'status',
            'external_status',
            'line_external_id',
            'product_external_id',
            'product_name',
            'quantity',
            'unit_price',
        ];
    }

    /**
     * Return the CSV export rows for a sales order.
     *
     * @return array<int, array<int, string>>
     */
    private function orderExportRows(SalesOrder $order): array
    {
        $contactName = $order->contact?->full_name ?? '';

        return $order->lines
            ->map(function (SalesOrderLine $line) use ($order, $contactName): array {
                return [
                    $order->external_source ?? '',
                    $order->external_id ?? '',
                    $this->formattedDate($order->order_date) ?? '',
                    $order->customer?->name ?? '',
                    $contactName,
                    $order->customer?->city ?? '',
                    $order->status,
                    $order->external_status ?? '',
                    $line->external_id ?? '',
                    $line->item?->external_id ?? '',
                    $line->item?->name ?? '',
                    (string) $line->quantity,
                    $this->formatCentsToAmount($line->unit_price_cents) ?? '',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Return the shared CRUD config for the Sales Orders page module.
     *
     * @return array<string, mixed>
     */
    private function ordersCrudConfig(): array
    {
        return [
            'resource' => 'orders',
            'endpoints' => [
                'list' => route('sales.orders.list'),
                'export' => route('sales.orders.export'),
                'create' => route('sales.orders.store'),
                'importPreview' => route('sales.orders.import.preview'),
                'importStore' => route('sales.orders.import.store'),
            ],
            'columns' => ['id', 'date', 'customer_name', 'city', 'status'],
            'headers' => [
                'id' => 'ID',
                'date' => 'Date',
                'customer_name' => 'Customer',
                'city' => 'City',
                'status' => 'Status',
            ],
            'sortable' => ['id', 'date', 'customer_name', 'city', 'status'],
            'labels' => [
                'searchPlaceholder' => 'Search orders',
                'exportTitle' => 'Export Orders',
                'exportAriaLabel' => 'Export Orders',
                'exportDescription' => 'Export sales orders as CSV using the current order list filters and sort when needed.',
                'exportFormatLabel' => 'CSV',
                'exportScopeLegend' => 'Export Scope',
                'exportCurrentOptionTitle' => 'Current filters and sort',
                'exportCurrentOptionDescription' => 'Uses the current search text and sort order from the orders list.',
                'exportAllOptionTitle' => 'All records',
                'exportAllOptionDescription' => 'Exports every sales order in the current tenant.',
                'exportCancelLabel' => 'Cancel',
                'exportSubmitLabel' => 'Export CSV',
                'exportUnavailableMessage' => 'Unable to export orders.',
                'importTitle' => 'Import Orders',
                'importAriaLabel' => 'Import Orders',
                'createTitle' => 'Add New Order',
                'createAriaLabel' => 'Add New Order',
                'emptyState' => 'No sales orders found.',
                'actionsAriaLabel' => 'Order actions',
            ],
            'permissions' => [
                'showExport' => Gate::allows('sales-sales-orders-manage'),
                'showImport' => Gate::allows('sales-sales-orders-manage'),
                'showCreate' => Gate::allows('sales-sales-orders-manage'),
            ],
            'rowDisplay' => [
                'columns' => [
                    'id' => ['kind' => 'text'],
                    'date' => ['kind' => 'text'],
                    'customer_name' => [
                        'kind' => 'linked-text',
                        'urlExpression' => 'record.show_url',
                    ],
                    'city' => ['kind' => 'text'],
                    'status' => ['kind' => 'text'],
                ],
            ],
            'mobileCard' => [
                'titleExpression' => '`Order #${record.id}`',
                'subtitleExpression' => 'orderCustomerSummary(record)',
                'bodyExpression' => 'orderStatusSummary(record)',
            ],
            'actions' => [
                [
                    'id' => 'view',
                    'label' => 'View',
                    'tone' => 'default',
                ],
                [
                    'id' => 'edit',
                    'label' => 'Edit',
                    'tone' => 'default',
                ],
            ],
        ];
    }

    /**
     * Return the shared import config for the Sales Orders import module.
     *
     * @return array<string, mixed>
     */
    private function ordersImportConfig(): array
    {
        $tenantId = (int) auth()->user()->tenant_id;

        return [
            'resource' => 'orders',
            'endpoints' => [
                'preview' => route('sales.orders.import.preview'),
                'store' => route('sales.orders.import.store'),
            ],
            'labels' => [
                'title' => 'Import Orders',
                'source' => 'Ecommerce Store',
                'submit' => 'Import Selected',
                'previewSearch' => 'Search preview records',
                'loadingPreviewDefault' => 'Loading preview...',
                'loadingPreviewFile' => 'Loading file preview...',
                'loadingPreviewExternal' => 'Loading WooCommerce preview...',
                'emptyStateDescription' => 'Select a WooCommerce connection or switch to file upload to start loading an order import preview.',
                'noBulkOptions' => 'No additional import options are available for this resource.',
                'previewDescription' => 'Review the import preview before confirming the selected orders.',
            ],
            'permissions' => [
                'canManageImports' => Gate::allows('sales-sales-orders-manage'),
                'canManageConnections' => Gate::allows('system-users-manage'),
            ],
            'messages' => [
                'previewUnavailable' => 'Unable to load order preview.',
                'importUnavailable' => 'Unable to import orders.',
                'fileReadError' => 'The selected CSV file could not be read.',
                'filePreviewUnavailable' => 'The selected CSV file could not be previewed.',
                'emptyFileRows' => 'The selected CSV file does not contain any order rows.',
                'missingFileHeaders' => 'The selected CSV file is missing one or more required order headers.',
                'emptySelection' => 'Select at least one order to import.',
            ],
            'connectorsPageUrl' => Gate::allows('system-users-manage')
                ? route('profile.connectors.index')
                : null,
            'sources' => array_merge([
                [
                    'value' => 'file-upload',
                    'label' => 'File Upload',
                    'enabled' => true,
                    'connected' => false,
                    'status' => 'local',
                    'status_label' => 'Local file',
                ],
            ], $this->availableSourcesForTenant($tenantId)),
            'rowBehavior' => [
                'hideDuplicatesByDefault' => false,
                'selectVisibleNonDuplicateRowsOnly' => true,
                'submitSelectedVisibleRowsOnly' => true,
                'duplicateFlagField' => 'is_duplicate',
                'selectionField' => 'selected',
            ],
            'previewDisplay' => [
                'titleExpression' => 'truncatedPreviewCustomerName(row)',
                'subtitleExpression' => 'compactPreviewMeta(row)',
                'bodyExpression' => '',
                'searchExpressions' => [
                    'row.date',
                    'row.external_id',
                    'row.external_source',
                    '(row.customer && row.customer.name)',
                    '(row.customer && row.customer.email)',
                    '(row.customer && row.customer.city)',
                    'row.external_status',
                ],
                'errorFields' => [
                    'external_status',
                    'lines',
                ],
            ],
        ];
    }

    /**
     * Return the available import sources and their tenant-scoped connection status.
     *
     * @return array<int, array<string, mixed>>
     */
    private function availableSourcesForTenant(int $tenantId): array
    {
        $connections = ExternalProductSourceConnection::query()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('source');

        $wooCommerce = $connections->get(ExternalProductSourceConnection::SOURCE_WOOCOMMERCE);

        return [
            [
                'value' => ExternalProductSourceConnection::SOURCE_WOOCOMMERCE,
                'label' => 'WooCommerce',
                'enabled' => true,
                'connected' => $wooCommerce?->isConnected() ?? false,
                'status' => $wooCommerce?->status ?? ExternalProductSourceConnection::STATUS_DISCONNECTED,
                'status_label' => ($wooCommerce?->isConnected() ?? false) ? 'Connected' : 'Disconnected',
            ],
            [
                'value' => 'shopify',
                'label' => 'Shopify',
                'enabled' => false,
                'connected' => false,
                'status' => 'placeholder',
                'status_label' => 'Coming soon',
            ],
        ];
    }

    /**
     * Return the connected source for the tenant when credentials are usable.
     */
    private function connectedSourceForTenant(int $tenantId, string $source): ?ExternalProductSourceConnection
    {
        $connection = ExternalProductSourceConnection::query()
            ->where('tenant_id', $tenantId)
            ->where('source', $source)
            ->first();

        if (! $connection || ! $connection->isConnected()) {
            return null;
        }

        return $connection;
    }

    /**
     * Return the stable disconnected-source JSON error payload.
     */
    private function notConnectedResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'The selected source is not connected.',
            'errors' => [
                'source' => ['The selected source is not connected.'],
            ],
            'meta' => [
                'connect_required' => true,
            ],
        ], 422);
    }

    /**
     * Annotate preview rows with tenant-scoped duplicate metadata.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function annotateDuplicatePreviewRows(int $tenantId, array $rows, ?string $defaultSource): array
    {
        $duplicateKeys = collect($rows)
            ->map(function (array $row) use ($defaultSource): ?string {
                $externalSource = $this->resolvedRowExternalSource($defaultSource, $row);
                $externalId = $this->normalizedExternalId($row['external_id'] ?? null);

                if ($externalSource === null || $externalId === null) {
                    return null;
                }

                return $externalSource . '|' . $externalId;
            })
            ->filter()
            ->unique()
            ->values();

        $existingKeys = SalesOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('external_source')
            ->whereNotNull('external_id')
            ->get(['external_source', 'external_id'])
            ->map(function (SalesOrder $order): ?string {
                $externalSource = $this->normalizedExternalSource($order->external_source);
                $externalId = $this->normalizedExternalId($order->external_id);

                if ($externalSource === null || $externalId === null) {
                    return null;
                }

                return $externalSource . '|' . $externalId;
            })
            ->filter(fn (?string $key): bool => $key !== null && $duplicateKeys->contains($key))
            ->flip();

        return array_values(array_map(function (array $row) use ($defaultSource, $existingKeys): array {
            $externalSource = $this->resolvedRowExternalSource($defaultSource, $row);
            $externalId = $this->normalizedExternalId($row['external_id'] ?? null);
            $duplicateKey = $externalSource !== null && $externalId !== null
                ? $externalSource . '|' . $externalId
                : null;
            $isDuplicate = $duplicateKey !== null && $existingKeys->has($duplicateKey);

            return array_merge($row, [
                'external_source' => $externalSource,
                'is_duplicate' => $isDuplicate,
                'duplicate_reason' => $isDuplicate
                    ? 'A sales order with the same external source and external ID already exists.'
                    : '',
                'selected' => ! $isDuplicate,
            ]);
        }, $rows));
    }

    /**
     * Create or refresh one imported order.
     *
     * @param  array<string, mixed>  $row
     * @return array{0: SalesOrder, 1: bool}
     */
    private function createOrRefreshImportedOrder(int $tenantId, string $source, array $row): array
    {
        $externalSource = $this->resolvedRowExternalSource($source, $row) ?? $source;
        $externalId = $this->normalizedExternalId($row['external_id'] ?? null) ?? '';
        $externalStatus = $this->resolvedImportedExternalStatus($row);
        $existingOrder = SalesOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(TRIM(external_source)) = ?', [$externalSource])
            ->whereRaw('TRIM(external_id) = ?', [$externalId])
            ->first();

        if ($existingOrder) {
            $existingOrder->forceFill([
                'external_status' => $externalStatus,
                'external_status_synced_at' => now(),
            ])->save();

            return [$existingOrder->fresh(['customer', 'contact', 'lines.item']), false];
        }

        $customer = $this->resolveImportedCustomer($tenantId, $externalSource, $row);
        $contact = $this->ensureImportedPrimaryContact($customer, $row);
        $localStatus = $this->localStatusForExternalStatus($externalStatus);
        $order = SalesOrder::query()->create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            'contact_id' => $contact?->id,
            'order_date' => $this->normalizedOrderDate($row['date'] ?? null),
            'status' => $localStatus,
            'external_source' => $externalSource,
            'external_id' => $externalId,
            'external_status' => $externalStatus,
            'external_status_synced_at' => now(),
        ]);

        foreach (($row['lines'] ?? []) as $line) {
            if (! is_array($line)) {
                continue;
            }

            $item = $this->resolveImportedItem($tenantId, $externalSource, $line);
            $quantity = bcadd((string) ($line['quantity'] ?? '0'), '0', self::SCALE);
            $unitPriceCents = $this->resolvedImportedUnitPriceCents($line);
            $currencyCode = strtoupper((string) (($line['currency_code'] ?? config('app.currency_code', 'USD')) ?: 'USD'));

            SalesOrderLine::query()->create([
                'tenant_id' => $tenantId,
                'sales_order_id' => $order->id,
                'item_id' => $item->id,
                'external_id' => $this->normalizedExternalId($line['external_id'] ?? null),
                'quantity' => $quantity,
                'unit_price_cents' => $unitPriceCents,
                'unit_price_currency_code' => $currencyCode,
                'line_total_cents' => bcmul($quantity, (string) $unitPriceCents, self::SCALE),
            ]);
        }

        return [$order->fresh(['customer', 'contact', 'lines.item']), true];
    }

    /**
     * Resolve or create the imported customer.
     *
     * @param  array<string, mixed>  $row
     */
    private function resolveImportedCustomer(int $tenantId, string $source, array $row): Customer
    {
        $customerRow = is_array($row['customer'] ?? null) ? $row['customer'] : [];
        $externalCustomerId = $this->normalizedExternalId($customerRow['external_id'] ?? null);
        $mapping = null;

        if ($externalCustomerId !== null) {
            $mapping = ExternalCustomerMapping::query()
                ->where('tenant_id', $tenantId)
                ->where('source', $source)
                ->where('external_customer_id', $externalCustomerId)
                ->first();
        }

        $customer = $mapping?->customer;

        if (! $customer) {
            $email = $this->normalizedEmail($customerRow['email'] ?? null);

            if ($email !== null) {
                $contact = CustomerContact::query()
                    ->where('tenant_id', $tenantId)
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->first();

                $customer = $contact?->customer;
            }
        }

        if (! $customer) {
            $customer = Customer::query()->create([
                'tenant_id' => $tenantId,
                'name' => (string) ($customerRow['name'] ?? 'Imported Customer'),
                'status' => Customer::STATUS_ACTIVE,
                'address_line_1' => $this->nullableString($customerRow['address_line_1'] ?? null),
                'address_line_2' => $this->nullableString($customerRow['address_line_2'] ?? null),
                'city' => $this->nullableString($customerRow['city'] ?? null),
                'region' => $this->nullableString($customerRow['region'] ?? null),
                'postal_code' => $this->nullableString($customerRow['postal_code'] ?? null),
                'country_code' => $this->nullableUpperString($customerRow['country_code'] ?? null),
            ]);
        }

        if ($externalCustomerId !== null) {
            ExternalCustomerMapping::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'source' => $source,
                    'external_customer_id' => $externalCustomerId,
                ],
                [
                    'customer_id' => $customer->id,
                ]
            );
        }

        return $customer;
    }

    /**
     * Ensure the imported customer has a first contact.
     *
     * @param  array<string, mixed>  $row
     */
    private function ensureImportedPrimaryContact(Customer $customer, array $row): ?CustomerContact
    {
        $customerRow = is_array($row['customer'] ?? null) ? $row['customer'] : [];
        $contactName = trim((string) ($row['contact_name'] ?? ''));
        $parsedName = $this->parseHumanName($contactName !== '' ? $contactName : (string) ($customerRow['name'] ?? ''));
        $email = $this->normalizedEmail($customerRow['email'] ?? null);

        if ($parsedName === null && $email === null) {
            return $customer->primaryContact()->first();
        }

        $contact = null;

        if ($email !== null) {
            $contact = $customer->contacts()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();
        }

        if (! $contact && $parsedName !== null) {
            $contact = $customer->contacts()
                ->where('first_name', $parsedName['first_name'])
                ->where('last_name', $parsedName['last_name'])
                ->first();
        }

        if ($contact) {
            if (! $contact->is_primary) {
                $customer->contacts()->update(['is_primary' => false]);
                $contact->forceFill(['is_primary' => true])->save();
            }

            return $contact;
        }

        $customer->contacts()->update(['is_primary' => false]);

        return CustomerContact::query()->create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'first_name' => $parsedName['first_name'] ?? 'Imported',
            'last_name' => $parsedName['last_name'] ?? 'Contact',
            'email' => $this->nullableLowerString($customerRow['email'] ?? null),
            'phone' => $this->nullableString($customerRow['phone'] ?? null),
            'role' => null,
            'is_primary' => true,
        ]);
    }

    /**
     * Resolve or create the imported sellable item for an order line.
     *
     * @param  array<string, mixed>  $line
     */
    private function resolveImportedItem(int $tenantId, string $source, array $line): Item
    {
        $productExternalId = $this->normalizedExternalId($line['product_external_id'] ?? null);

        if ($productExternalId !== null) {
            $existing = Item::query()
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(TRIM(external_source)) = ?', [$source])
                ->whereRaw('TRIM(external_id) = ?', [$productExternalId])
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $fallbackUom = $this->ensureImportedFallbackUom($tenantId);

        return Item::query()->create([
            'tenant_id' => $tenantId,
            'name' => (string) ($line['name'] ?? 'Imported Item'),
            'base_uom_id' => $fallbackUom->id,
            'is_active' => false,
            'is_purchasable' => false,
            'is_sellable' => true,
            'is_manufacturable' => false,
            'default_price_cents' => $this->resolvedImportedUnitPriceCents($line),
            'default_price_currency_code' => strtoupper((string) (($line['currency_code'] ?? 'USD') ?: 'USD')),
            'external_source' => $source,
            'external_id' => $productExternalId,
        ]);
    }

    /**
     * Ensure a fallback unit of measure exists for imported line items.
     */
    private function ensureImportedFallbackUom(int $tenantId): Uom
    {
        $existing = Uom::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $category = UomCategory::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'name' => 'Imported Units',
            ]
        );

        return Uom::query()->create([
            'tenant_id' => $tenantId,
            'uom_category_id' => $category->id,
            'name' => 'Each',
            'symbol' => 'ea',
            'display_precision' => 6,
        ]);
    }

    /**
     * Return a validation response for unsupported external statuses.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function invalidExternalStatusResponse(array $rows): ?JsonResponse
    {
        foreach ($rows as $index => $row) {
            $externalStatus = is_array($row) ? $this->resolvedImportedExternalStatus($row) : '';

            if ($this->localStatusForExternalStatus($externalStatus) !== null) {
                continue;
            }

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    "rows.{$index}.external_status" => ['The external order status is not supported.'],
                ],
            ], 422);
        }

        return null;
    }

    /**
     * Map a WooCommerce status into the local sales order lifecycle.
     */
    private function localStatusForExternalStatus(string $externalStatus): ?string
    {
        $normalized = mb_strtolower(trim($externalStatus));
        $normalized = str_replace(['_', '-'], ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return match ($normalized) {
            'completed' => SalesOrder::STATUS_COMPLETED,
            'cancelled', 'canceled', 'refunded', 'failed' => SalesOrder::STATUS_CANCELLED,
            'processing', 'pending payment', 'pending', 'on hold', 'draft', 'new', 'open', 'packing', 'packed', 'shipping' => SalesOrder::STATUS_OPEN,
            default => null,
        };
    }

    /**
     * Resolve the normalized external source for a preview or import row.
     *
     * @param  array<string, mixed>  $row
     */
    private function resolvedRowExternalSource(?string $defaultSource, array $row): ?string
    {
        $rowSource = $this->normalizedExternalSource($row['external_source'] ?? null);

        if ($rowSource !== null) {
            return $rowSource;
        }

        return $this->normalizedExternalSource($defaultSource);
    }

    /**
     * Normalize an external source value for exact duplicate matching.
     */
    private function normalizedExternalSource(mixed $value): ?string
    {
        $normalized = mb_strtolower(trim((string) ($value ?? '')));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Normalize an external ID value for exact duplicate matching.
     */
    private function normalizedExternalId(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Normalize an email value for duplicate matching.
     */
    private function normalizedEmail(mixed $value): ?string
    {
        $normalized = mb_strtolower(trim((string) ($value ?? '')));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Convert a nullable string input into a trimmed nullable string.
     */
    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Convert a nullable string input into an uppercase trimmed nullable string.
     */
    private function nullableUpperString(mixed $value): ?string
    {
        $normalized = $this->nullableString($value);

        return $normalized === null ? null : strtoupper($normalized);
    }

    /**
     * Convert a nullable string input into a lowercase trimmed nullable string.
     */
    private function nullableLowerString(mixed $value): ?string
    {
        $normalized = $this->nullableString($value);

        return $normalized === null ? null : mb_strtolower($normalized);
    }

    /**
     * Parse a human name into first and last name components.
     *
     * @return array{first_name: string, last_name: string}|null
     */
    private function parseHumanName(string $name): ?array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($name)) ?? trim($name);

        if ($normalized === '') {
            return null;
        }

        $parts = explode(' ', $normalized);
        $firstName = array_shift($parts) ?? '';
        $lastName = trim(implode(' ', $parts));

        return [
            'first_name' => $firstName !== '' ? $firstName : 'Imported',
            'last_name' => $lastName !== '' ? $lastName : 'Contact',
        ];
    }

    /**
     * Normalize a submitted order date to the canonical Y-m-d form.
     */
    private function normalizedOrderDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->toDateString();
    }

    /**
     * Format an order date value for JSON and CSV payloads.
     */
    private function formattedDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        return Carbon::parse((string) $value)->toDateString();
    }

    /**
     * Resolve the external status used for import mapping and sync fields.
     *
     * @param  array<string, mixed>  $row
     */
    private function resolvedImportedExternalStatus(array $row): string
    {
        $externalStatus = trim((string) ($row['external_status'] ?? ''));

        if ($externalStatus !== '') {
            return $externalStatus;
        }

        return trim((string) ($row['status'] ?? ''));
    }

    /**
     * Resolve an imported unit price into integer cents without float math.
     *
     * @param  array<string, mixed>  $line
     */
    private function resolvedImportedUnitPriceCents(array $line): int
    {
        if (array_key_exists('unit_price_cents', $line) && $line['unit_price_cents'] !== null && $line['unit_price_cents'] !== '') {
            return (int) $line['unit_price_cents'];
        }

        return $this->normalizeAmountToCents((string) ($line['unit_price'] ?? '0'));
    }

    /**
     * Normalize a numeric amount string to integer cents without float casting.
     */
    private function normalizeAmountToCents(string $amount): int
    {
        $normalized = trim($amount);

        if ($normalized === '') {
            return 0;
        }

        if (str_contains($normalized, '.')) {
            [$whole, $decimal] = explode('.', $normalized, 2);
            $decimal = str_pad(substr($decimal, 0, 2), 2, '0');

            return (((int) $whole) * 100) + ((int) $decimal);
        }

        return ((int) $normalized) * 100;
    }

    /**
     * Format cents into a decimal amount string.
     */
    private function formatCentsToAmount(?int $cents): ?string
    {
        if ($cents === null) {
            return null;
        }

        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
