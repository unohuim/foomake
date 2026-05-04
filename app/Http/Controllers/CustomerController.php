<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
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
    private const SCALE = 6;

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
            'customers' => $customers->map(fn (Customer $customer) => $this->customerIndexData($customer))->values()->all(),
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
        Gate::authorize('sales-customers-view');

        $customer->load('contacts', 'salesOrders.contact', 'salesOrders.customer', 'salesOrders.lines.item');
        $canManage = Gate::allows('sales-customers-manage');
        $canManageOrders = Gate::allows('sales-sales-orders-manage');
        $customersForOrders = $canManageOrders
            ? Customer::query()->with('contacts')->orderBy('name')->get()
            : collect();
        $sellableItems = $canManageOrders
            ? Item::query()->where('is_sellable', true)->orderBy('name')->get()
            : collect();

        $payload = [
            'customer' => $this->customerData($customer),
            'contacts' => $customer->contacts
                ->map(fn (CustomerContact $contact): array => $this->contactData($contact))
                ->values()
                ->all(),
            'canManage' => $canManage,
            'canManageOrders' => $canManageOrders,
            'updateUrl' => $canManage ? route('sales.customers.update', $customer) : null,
            'deleteUrl' => $canManage ? route('sales.customers.destroy', $customer) : null,
            'contactsStoreUrl' => $canManage ? route('sales.customers.contacts.store', $customer) : null,
            'contactsBaseUrl' => $canManage ? url('/sales/customers/' . $customer->id . '/contacts') : null,
            'orders' => $canManageOrders
                ? $customer->salesOrders->map(fn (SalesOrder $order): array => $this->orderData($order))->values()->all()
                : [],
            'orderCustomers' => $canManageOrders
                ? $customersForOrders->map(fn (Customer $entry): array => $this->customerOrderOptionData($entry))->values()->all()
                : [],
            'orderItems' => $canManageOrders
                ? $sellableItems->map(fn (Item $item): array => $this->sellableItemData($item))->values()->all()
                : [],
            'ordersStoreUrl' => $canManageOrders ? route('sales.orders.store') : null,
            'ordersUpdateUrlBase' => $canManageOrders ? url('/sales/orders') : null,
            'ordersDeleteUrlBase' => $canManageOrders ? url('/sales/orders') : null,
            'ordersLineStoreUrlBase' => $canManageOrders ? url('/sales/orders') : null,
            'orderStatuses' => SalesOrder::statuses(),
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
     * Build the customer index payload.
     *
     * @return array<string, int|string|null>
     */
    private function customerIndexData(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'status' => $customer->status,
            'notes' => null,
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
     * Build the customer contact response payload.
     *
     * @return array<string, int|string|bool|null>
     */
    private function contactData(CustomerContact $contact): array
    {
        return [
            'id' => $contact->id,
            'tenant_id' => $contact->tenant_id,
            'customer_id' => $contact->customer_id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'full_name' => $contact->full_name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'role' => $contact->role,
            'is_primary' => $contact->is_primary,
        ];
    }

    /**
     * Build the customer order response payload.
     *
     * @return array<string, int|string|null|array<int, array<string, int|string|null>>>
     */
    private function orderData(SalesOrder $order): array
    {
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
            'contact_name' => $order->contact?->full_name,
            'status' => $order->status,
            'lines' => $lines,
            'line_count' => count($lines),
            'order_total_cents' => $orderTotalCents,
            'order_total_amount' => bcdiv($orderTotalCents, '100', self::SCALE),
        ];
    }

    /**
     * Build customer order form options.
     *
     * @return array<string, int|string|null|array<int, array<string, int|string|bool|null>>>
     */
    private function customerOrderOptionData(Customer $customer): array
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
    private function sellableItemData(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'default_price_cents' => $item->default_price_cents,
            'default_price_currency_code' => $item->default_price_currency_code,
        ];
    }

    /**
     * Build the customer order line payload.
     *
     * @return array<string, int|string|null>
     */
    private function lineData(SalesOrderLine $line): array
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
