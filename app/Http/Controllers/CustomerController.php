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
            ->where('status', Customer::STATUS_ACTIVE)
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

        $validated = $request->validate($this->storeRules());

        $customer = Customer::query()->create(array_merge($this->addressAttributes($validated), [
            'tenant_id' => $request->user()->tenant_id,
            'name' => $validated['name'],
            'status' => $validated['status'] ?? Customer::STATUS_ACTIVE,
            'notes' => $validated['notes'] ?? null,
        ]));

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

        $validated = $request->validate($this->updateRules());

        $customer->update(array_merge($this->addressAttributes($validated), [
            'name' => $validated['name'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]));

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
            'address_line_1' => $customer->address_line_1,
            'address_line_2' => $customer->address_line_2,
            'city' => $customer->city,
            'region' => $customer->region,
            'postal_code' => $customer->postal_code,
            'country_code' => $customer->country_code,
            'formatted_address' => $customer->formatted_address,
            'address_summary' => $this->addressSummary($customer),
            'latitude' => $customer->latitude,
            'longitude' => $customer->longitude,
            'address_provider' => $customer->address_provider,
            'address_provider_id' => $customer->address_provider_id,
            'show_url' => route('sales.customers.show', $customer),
        ];
    }

    /**
     * Return validation rules for create requests.
     *
     * @return array<string, list<string|\Illuminate\Contracts\Validation\ValidationRule|Rule>>
     */
    private function storeRules(): array
    {
        return array_merge($this->addressRules(), [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(Customer::statuses())],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * Return validation rules for update requests.
     *
     * @return array<string, list<string|\Illuminate\Contracts\Validation\ValidationRule|Rule>>
     */
    private function updateRules(): array
    {
        return array_merge($this->addressRules(), [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(Customer::statuses())],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * Return address validation rules shared by store and update.
     *
     * @return array<string, list<string>>
     */
    private function addressRules(): array
    {
        return [
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha:ascii'],
            'formatted_address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'address_provider' => ['nullable', 'string', 'max:255'],
            'address_provider_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Extract address attributes from validated data.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function addressAttributes(array $validated): array
    {
        return [
            'address_line_1' => $validated['address_line_1'] ?? null,
            'address_line_2' => $validated['address_line_2'] ?? null,
            'city' => $validated['city'] ?? null,
            'region' => $validated['region'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'country_code' => $validated['country_code'] ?? null,
            'formatted_address' => $validated['formatted_address'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'address_provider' => $validated['address_provider'] ?? null,
            'address_provider_id' => $validated['address_provider_id'] ?? null,
        ];
    }

    /**
     * Build a concise address summary for index/table displays.
     */
    private function addressSummary(Customer $customer): ?string
    {
        if ($customer->formatted_address !== null && $customer->formatted_address !== '') {
            return $customer->formatted_address;
        }

        $parts = array_filter([
            $customer->address_line_1,
            $customer->address_line_2,
            $customer->city,
            $customer->region,
            $customer->postal_code,
            $customer->country_code,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }
}
