<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sales\StoreSalesOrderRequest;
use App\Http\Requests\Sales\UpdateSalesOrderRequest;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\SalesOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Handle draft sales order CRUD.
 */
class SalesOrderController extends Controller
{
    /**
     * Display the sales orders index.
     */
    public function index(): View
    {
        Gate::authorize('sales-sales-orders-manage');

        $orders = SalesOrder::query()
            ->with(['customer', 'contact'])
            ->orderByDesc('created_at')
            ->get();

        $customers = Customer::query()
            ->with('contacts')
            ->orderBy('name')
            ->get();

        $payload = [
            'orders' => $orders->map(fn (SalesOrder $order): array => $this->orderData($order))->values()->all(),
            'customers' => $customers->map(fn (Customer $customer): array => $this->customerOptionData($customer))->values()->all(),
            'storeUrl' => route('sales.orders.store'),
            'updateUrlBase' => url('/sales/orders'),
            'deleteUrlBase' => url('/sales/orders'),
            'csrfToken' => csrf_token(),
            'statuses' => SalesOrder::statuses(),
        ];

        return view('sales.orders.index', [
            'payload' => $payload,
        ]);
    }

    /**
     * Store a new draft sales order.
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

        $order->load(['customer', 'contact']);

        return response()->json([
            'data' => $this->orderData($order),
        ], 201);
    }

    /**
     * Update an existing draft sales order.
     */
    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesOrder): JsonResponse
    {
        Gate::authorize('sales-sales-orders-manage');

        $customer = Customer::query()
            ->with('contacts')
            ->findOrFail((int) $request->validated('customer_id'));

        $salesOrder->update([
            'customer_id' => $customer->id,
            'contact_id' => $this->resolveContactId($request, $customer, $salesOrder),
            'status' => SalesOrder::STATUS_DRAFT,
        ]);

        $salesOrder->load(['customer', 'contact']);

        return response()->json([
            'data' => $this->orderData($salesOrder),
        ]);
    }

    /**
     * Delete a draft sales order.
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
     * @return array<string, int|string|null>
     */
    public function orderData(SalesOrder $order): array
    {
        $contactName = null;

        if ($order->contact) {
            $contactName = $order->contact->full_name;
        }

        return [
            'id' => $order->id,
            'customer_id' => $order->customer_id,
            'customer_name' => $order->customer?->name,
            'contact_id' => $order->contact_id,
            'contact_name' => $contactName,
            'status' => $order->status,
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
}
