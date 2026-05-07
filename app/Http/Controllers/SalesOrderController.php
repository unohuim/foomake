<?php

namespace App\Http\Controllers;

use App\Actions\Workflows\ResolveSalesWorkflowStageAction;
use App\Http\Requests\Sales\StoreSalesOrderRequest;
use App\Http\Requests\Sales\UpdateSalesOrderRequest;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Handle sales order CRUD.
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
            ->with(['customer', 'contact', 'lines.item'])
            ->orderByDesc('created_at')
            ->get();

        $customers = Customer::query()
            ->with('contacts')
            ->orderBy('name')
            ->get();
        $sellableItems = Item::query()
            ->where('is_sellable', true)
            ->orderBy('name')
            ->get();

        $payload = [
            'orders' => $orders->map(fn (SalesOrder $order): array => $this->orderData($order))->values()->all(),
            'customers' => $customers->map(fn (Customer $customer): array => $this->customerOptionData($customer))->values()->all(),
            'sellableItems' => $sellableItems->map(fn (Item $item): array => $this->sellableItemData($item))->values()->all(),
            'storeUrl' => route('sales.orders.store'),
            'updateUrlBase' => url('/sales/orders'),
            'deleteUrlBase' => url('/sales/orders'),
            'lineStoreUrlBase' => url('/sales/orders'),
            'csrfToken' => csrf_token(),
            'statuses' => SalesOrder::statuses(),
        ];

        return view('sales.orders.index', [
            'payload' => $payload,
        ]);
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
            'customer_id' => $order->customer_id,
            'customer_name' => $order->customer?->name,
            'contact_id' => $order->contact_id,
            'contact_name' => $contactName,
            'status' => $order->status,
            'can_edit' => $order->isEditable(),
            'can_manage_lines' => $order->allowsLineMutations(),
            'available_status_transitions' => $order->availableTransitions(),
            'status_update_url' => route('sales.orders.status.update', $order),
            'lines' => $lines,
            'line_count' => count($lines),
            'order_total_cents' => $orderTotalCents,
            'order_total_amount' => bcdiv($orderTotalCents, '100', self::SCALE),
            'current_stage_tasks' => $this->currentStageTasksData($order),
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
     * Return the shared non-editable response for terminal sales orders.
     */
    private function editableOrderResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Only draft or open sales orders can be edited.',
            'errors' => [
                'customer_id' => [],
                'contact_id' => [],
            ],
        ], 422);
    }
}
