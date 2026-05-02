<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Handle sales customer CRUD.
 */
class CustomerController extends Controller
{
    /**
     * Display the customers index.
     */
    public function index(): View
    {
        Gate::authorize('sales-customers-manage');

        $customers = Customer::query()
            ->where('status', '!=', Customer::STATUS_ARCHIVED)
            ->orderBy('name')
            ->get();

        $payload = [
            'customers' => $customers->map(fn (Customer $customer) => $this->customerData($customer))->values()->all(),
            'storeUrl' => route('sales.customers.store'),
            'updateUrlBase' => url('/sales/customers'),
            'csrfToken' => csrf_token(),
            'statuses' => Customer::statuses(),
        ];

        return view('sales.customers.index', [
            'customers' => $customers,
            'payload' => $payload,
        ]);
    }

    /**
     * Display the customer detail page.
     */
    public function show(Customer $customer): View
    {
        Gate::authorize('sales-customers-manage');

        $payload = [
            'customer' => $this->customerData($customer),
            'updateUrl' => route('sales.customers.update', $customer),
            'deleteUrl' => route('sales.customers.destroy', $customer),
            'indexUrl' => route('sales.customers.index'),
            'csrfToken' => csrf_token(),
            'statuses' => Customer::statuses(),
        ];

        return view('sales.customers.show', [
            'customer' => $customer,
            'payload' => $payload,
        ]);
    }

    /**
     * Store a new customer.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('sales-customers-manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(Customer::statuses())],
            'notes' => ['nullable', 'string'],
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $validated['name'],
            'status' => $validated['status'] ?? Customer::STATUS_ACTIVE,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'data' => $this->customerData($customer),
        ], 201);
    }

    /**
     * Update an existing customer.
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('sales-customers-manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(Customer::statuses())],
            'notes' => ['nullable', 'string'],
        ]);

        $customer->update([
            'name' => $validated['name'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'data' => $this->customerData($customer->fresh()),
        ]);
    }

    /**
     * Archive the customer.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        Gate::authorize('sales-customers-manage');

        $customer->update([
            'status' => Customer::STATUS_ARCHIVED,
        ]);

        return response()->json([
            'data' => $this->customerData($customer->fresh()),
            'message' => 'Archived.',
        ]);
    }

    /**
     * Build the customer response payload.
     *
     * @return array<string, int|string|null>
     */
    private function customerData(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'status' => $customer->status,
            'notes' => $customer->notes,
            'show_url' => route('sales.customers.show', $customer),
        ];
    }
}
