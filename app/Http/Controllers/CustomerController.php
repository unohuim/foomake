<?php

namespace App\Http\Controllers;

use App\Actions\Workflows\ResolveSalesWorkflowStageAction;
use App\Http\Requests\Sales\ImportExternalCustomersRequest;
use App\Http\Requests\Sales\ListSalesCustomersRequest;
use App\Http\Requests\Sales\PreviewExternalCustomerImportRequest;
use App\Integrations\WooCommerce\WooCommerceException;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\ExternalCustomerMapping;
use App\Models\ExternalProductSourceConnection;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Task;
use App\Services\WooCommerceCustomerPreviewService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
            ->with('primaryContact')
            ->where('status', Customer::STATUS_ACTIVE)
            ->orderBy('name')
            ->get();
        $crudConfig = $this->customersCrudConfig();
        $importConfig = $this->customersImportConfig();

        $payload = [
            'customers' => $customers->map(fn (Customer $customer) => $this->customerIndexData($customer))->values()->all(),
            'storeUrl' => $crudConfig['endpoints']['create'],
            'updateUrlBase' => url('/sales/customers'),
            'navigationStateUrl' => route('navigation.state'),
            'csrfToken' => csrf_token(),
            'statuses' => Customer::statuses(),
            'sources' => $importConfig['sources'],
            'canManageImports' => $importConfig['permissions']['canManageImports'],
            'canManageConnections' => $importConfig['permissions']['canManageConnections'],
            'connectorsPageUrl' => $importConfig['connectorsPageUrl'],
            'previewUrl' => $importConfig['endpoints']['preview'],
            'importUrl' => $importConfig['endpoints']['store'],
        ];

        return view('sales.customers.index', [
            'crudConfig' => $crudConfig,
            'importConfig' => $importConfig,
            'customers' => $customers,
            'payload' => $payload,
        ]);
    }

    /**
     * Return the Sales Customers list read model for the page module.
     */
    public function list(ListSalesCustomersRequest $request): JsonResponse
    {
        Gate::authorize('sales-customers-manage');

        $validated = $request->validated();
        $crudConfig = $this->customersCrudConfig();
        $search = trim((string) ($validated['search'] ?? ''));
        $sortColumn = (string) ($validated['sort'] ?? 'name');
        $direction = (string) ($validated['direction'] ?? 'asc');
        $customers = $this->customersQuery($search, $sortColumn, $direction)->get();

        return response()->json([
            'data' => $customers
                ->map(fn (Customer $customer): array => $this->customerIndexData($customer))
                ->values()
                ->all(),
            'meta' => [
                'search' => $search,
                'sort' => [
                    'column' => $sortColumn,
                    'direction' => $direction,
                ],
                'allowed_sort_columns' => $crudConfig['sortable'],
                'total' => $customers->count(),
            ],
        ]);
    }

    /**
     * Download a CSV export for sales customers.
     */
    public function export(ListSalesCustomersRequest $request): StreamedResponse
    {
        Gate::authorize('sales-customers-manage');

        $validated = $request->validated();
        $scope = (string) ($validated['scope'] ?? 'current');
        $search = $scope === 'all'
            ? ''
            : trim((string) ($validated['search'] ?? ''));
        $sortColumn = $scope === 'all'
            ? 'name'
            : (string) ($validated['sort'] ?? 'name');
        $direction = $scope === 'all'
            ? 'asc'
            : (string) ($validated['direction'] ?? 'asc');
        $customers = $this->customersQuery($search, $sortColumn, $direction)->get();

        return response()->streamDownload(function () use ($customers): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $this->customerExportHeaders());

            foreach ($customers as $customer) {
                fputcsv($handle, $this->customerExportRow($customer));
            }

            fclose($handle);
        }, 'customers-export.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=customers-export.csv',
        ]);
    }

    /**
     * Return WooCommerce-backed preview rows for the selected source.
     */
    public function previewImport(
        PreviewExternalCustomerImportRequest $request,
        WooCommerceCustomerPreviewService $previewService
    ): JsonResponse {
        $source = (string) $request->validated('source');

        if ($source === 'file-upload') {
            return response()->json([
                'data' => [
                    'source' => $source,
                    'is_connected' => true,
                    'rows' => $request->validated('rows', []),
                ],
            ]);
        }

        $connection = $this->connectedSourceForTenant((int) $request->user()->tenant_id, $source);

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
                'message' => 'The external customer preview could not be loaded.',
                'errors' => [
                    'source' => [$exception->getMessage()],
                ],
            ], 422);
        }

        return response()->json([
            'data' => [
                'source' => $source,
                'is_connected' => true,
                'rows' => $rows,
            ],
        ]);
    }

    /**
     * Import selected preview rows as normal customers with external mappings.
     */
    public function storeImport(ImportExternalCustomersRequest $request): JsonResponse
    {
        $source = (string) $request->validated('source');

        if ($source !== 'file-upload' && ! $this->connectedSourceForTenant((int) $request->user()->tenant_id, $source)) {
            return $this->notConnectedResponse();
        }

        $tenantId = (int) $request->user()->tenant_id;

        /** @var Collection<int, Customer> $imported */
        $imported = collect();

        DB::transaction(function () use ($request, $tenantId, $source, &$imported): void {
            $imported = collect($request->validated('rows'))
                ->map(fn (array $row): Customer => $this->createOrMatchImportedCustomer($tenantId, $source, $row));
        });

        return response()->json([
            'data' => [
                'imported_count' => $imported->count(),
                'imported' => $imported
                    ->map(fn (Customer $customer): array => $this->customerData($customer->fresh()))
                    ->values()
                    ->all(),
            ],
        ], 201);
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
            'email' => $customer->primaryContact?->email,
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
            'email' => $customer->primaryContact?->email,
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
     * Build the filtered and sorted customers query shared by list and export.
     */
    private function customersQuery(string $search, string $sortColumn, string $direction)
    {
        $query = Customer::query()
            ->with('primaryContact')
            ->where('status', Customer::STATUS_ACTIVE);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('primaryContact', function ($contactQuery) use ($search): void {
                        $contactQuery->where('email', 'like', '%' . $search . '%');
                    });
            });
        }

        match ($sortColumn) {
            'email' => $query
                ->leftJoin('customer_contacts as primary_contacts', function ($join): void {
                    $join->on('primary_contacts.customer_id', '=', 'customers.id')
                        ->on('primary_contacts.tenant_id', '=', 'customers.tenant_id')
                        ->where('primary_contacts.is_primary', '=', 1);
                })
                ->select('customers.*')
                ->orderByRaw(
                    "CASE WHEN primary_contacts.email IS NULL OR primary_contacts.email = '' THEN 1 ELSE 0 END asc"
                )
                ->orderBy('primary_contacts.email', $direction)
                ->orderBy('customers.name'),
            default => $query->orderBy('customers.name', $direction),
        };

        return $query;
    }

    /**
     * Return the CSV headers for customer exports.
     *
     * @return array<int, string>
     */
    private function customerExportHeaders(): array
    {
        return [
            'name',
            'email',
            'status',
            'address_line_1',
            'address_line_2',
            'city',
            'region',
            'postal_code',
            'country_code',
            'formatted_address',
        ];
    }

    /**
     * Return one CSV row for a customer export.
     *
     * @return array<int, string|null>
     */
    private function customerExportRow(Customer $customer): array
    {
        return [
            $customer->name,
            $customer->primaryContact?->email,
            $customer->status,
            $customer->address_line_1,
            $customer->address_line_2,
            $customer->city,
            $customer->region,
            $customer->postal_code,
            $customer->country_code,
            $customer->formatted_address,
        ];
    }

    /**
     * Create or match an imported customer using mapping-first resolution.
     *
     * @param  array<string, mixed>  $row
     */
    private function createOrMatchImportedCustomer(int $tenantId, string $source, array $row): Customer
    {
        $externalId = (string) $row['external_id'];

        $mapping = ExternalCustomerMapping::query()
            ->where('tenant_id', $tenantId)
            ->where('source', $source)
            ->where('external_customer_id', $externalId)
            ->first();

        $customer = $mapping?->customer;

        if (! $customer) {
            $customer = $this->matchExistingCustomerByEmail($tenantId, (string) ($row['email'] ?? ''));
        }

        if (! $customer) {
            $customer = Customer::query()->create(array_merge(
                $this->importedAddressAttributes($row),
                [
                    'tenant_id' => $tenantId,
                    'name' => (string) $row['name'],
                    'status' => Customer::STATUS_ACTIVE,
                    'notes' => null,
                ]
            ));
        }

        ExternalCustomerMapping::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'source' => $source,
                'external_customer_id' => $externalId,
            ],
            [
                'customer_id' => $customer->id,
            ]
        );

        if ($source === ExternalProductSourceConnection::SOURCE_WOOCOMMERCE) {
            $this->syncWooImportedPrimaryContact($customer, $row);
        }

        return $customer;
    }

    /**
     * Create a primary contact for Woo imports when a human name can be extracted.
     *
     * @param  array<string, mixed>  $row
     */
    private function syncWooImportedPrimaryContact(Customer $customer, array $row): void
    {
        $parsedName = $this->parseHumanName((string) ($row['name'] ?? ''));

        if ($parsedName === null) {
            return;
        }

        $email = $this->nullableLowerString($row['email'] ?? null);
        $phone = $this->nullableString($row['phone'] ?? null);

        $existingContact = $customer->contacts()
            ->where('first_name', $parsedName['first_name'])
            ->where('last_name', $parsedName['last_name'])
            ->when($email !== null, fn ($query) => $query->where('email', $email))
            ->first();

        if (! $existingContact && $email !== null) {
            $existingContact = $customer->contacts()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();
        }

        if ($existingContact) {
            return;
        }

        $customer->contacts()->update([
            'is_primary' => false,
        ]);

        CustomerContact::query()->create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'first_name' => $parsedName['first_name'],
            'last_name' => $parsedName['last_name'],
            'email' => $email,
            'phone' => $phone,
            'role' => null,
            'is_primary' => true,
        ]);
    }

    /**
     * Parse a human name into first and last name parts.
     *
     * @return array{first_name: string, last_name: string}|null
     */
    private function parseHumanName(string $name): ?array
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $name) ?? '');

        if ($normalized === '') {
            return null;
        }

        $parts = explode(' ', $normalized);

        if (count($parts) < 2) {
            return null;
        }

        $firstName = array_shift($parts);
        $lastName = trim(implode(' ', $parts));

        if ($firstName === '' || $lastName === '') {
            return null;
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];
    }

    /**
     * Match an existing customer by a tenant-scoped contact email.
     */
    private function matchExistingCustomerByEmail(int $tenantId, string $email): ?Customer
    {
        $normalizedEmail = mb_strtolower(trim($email));

        if ($normalizedEmail === '') {
            return null;
        }

        $customerId = CustomerContact::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->value('customer_id');

        if (! $customerId) {
            return null;
        }

        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->find($customerId);
    }

    /**
     * Build imported address attributes from a preview row.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function importedAddressAttributes(array $row): array
    {
        return [
            'address_line_1' => $this->nullableString($row['address_line_1'] ?? null),
            'address_line_2' => $this->nullableString($row['address_line_2'] ?? null),
            'city' => $this->nullableString($row['city'] ?? null),
            'region' => $this->nullableString($row['region'] ?? null),
            'postal_code' => $this->nullableString($row['postal_code'] ?? null),
            'country_code' => $this->nullableUpperString($row['country_code'] ?? null),
            'formatted_address' => null,
            'latitude' => null,
            'longitude' => null,
            'address_provider' => null,
            'address_provider_id' => null,
        ];
    }

    /**
     * Normalize nullable imported strings.
     */
    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Normalize nullable imported upper-case strings.
     */
    private function nullableUpperString(mixed $value): ?string
    {
        $normalized = $this->nullableString($value);

        return $normalized === null ? null : strtoupper($normalized);
    }

    /**
     * Normalize nullable imported lower-case strings.
     */
    private function nullableLowerString(mixed $value): ?string
    {
        $normalized = $this->nullableString($value);

        return $normalized === null ? null : mb_strtolower($normalized);
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
     * @return array<string, int|string|bool|null|array<int, array<string, int|string|null>>|list<string>>
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
     * Build the shared CRUD config for the Sales Customers page module.
     *
     * @return array<string, mixed>
     */
    private function customersCrudConfig(): array
    {
        return [
            'resource' => 'customers',
            'endpoints' => [
                'list' => route('sales.customers.list'),
                'export' => route('sales.customers.export'),
                'create' => route('sales.customers.store'),
                'importPreview' => route('sales.customers.import.preview'),
                'importStore' => route('sales.customers.import.store'),
            ],
            'columns' => ['name', 'email', 'address_summary'],
            'headers' => [
                'name' => 'Name',
                'email' => 'Email',
                'address_summary' => 'Address',
            ],
            'sortable' => ['name', 'email'],
            'labels' => [
                'searchPlaceholder' => 'Search customers',
                'exportTitle' => 'Export Customers',
                'exportAriaLabel' => 'Export Customers',
                'importTitle' => 'Import Customers',
                'importAriaLabel' => 'Import Customers',
                'createTitle' => 'Add New Customer',
                'createAriaLabel' => 'Add New Customer',
                'emptyState' => 'No customers found.',
                'actionsAriaLabel' => 'Customer actions',
            ],
            'permissions' => [
                'showExport' => Gate::allows('sales-customers-manage'),
                'showImport' => Gate::allows('system-users-manage'),
                'showCreate' => Gate::allows('sales-customers-manage'),
            ],
            'rowDisplay' => [
                'columns' => [
                    'name' => [
                        'kind' => 'linked-text',
                        'urlExpression' => 'record.show_url',
                    ],
                    'email' => ['kind' => 'text'],
                    'address_summary' => ['kind' => 'text'],
                ],
            ],
            'mobileCard' => [
                'titleExpression' => "record.name || '—'",
                'subtitleExpression' => "record.email || '—'",
                'bodyExpression' => "record.address_summary || '—'",
            ],
            'actions' => [
                [
                    'id' => 'edit',
                    'label' => 'Edit',
                    'tone' => 'default',
                ],
                [
                    'id' => 'archive',
                    'label' => 'Archive',
                    'tone' => 'warning',
                ],
            ],
        ];
    }

    /**
     * Return the shared import config for the Sales Customers import module.
     *
     * @return array<string, mixed>
     */
    private function customersImportConfig(): array
    {
        $canManageImports = Gate::allows('system-users-manage');

        return [
            'resource' => 'customers',
            'endpoints' => [
                'preview' => route('sales.customers.import.preview'),
                'store' => route('sales.customers.import.store'),
            ],
            'labels' => [
                'title' => 'Import Customers',
                'source' => 'Source',
                'submit' => 'Confirm Import',
                'previewSearch' => 'Search preview records',
                'loadingPreviewDefault' => 'Loading preview...',
                'loadingPreviewFile' => 'Loading file preview...',
                'loadingPreviewExternal' => 'Loading WooCommerce preview...',
                'emptyStateDescription' => 'Select a WooCommerce connection or switch to file upload to start loading an import preview.',
                'noBulkOptions' => 'No additional import options are available for this resource.',
                'previewDescription' => 'Review the import preview before confirming the selected records.',
            ],
            'permissions' => [
                'canManageImports' => $canManageImports,
                'canManageConnections' => $canManageImports,
            ],
            'messages' => [
                'importUnavailable' => 'Unable to import customers.',
                'emptyFileRows' => 'The selected CSV file does not contain any customer rows.',
                'missingFileHeaders' => 'The selected CSV file is missing one or more required customer headers.',
                'emptySelection' => 'Select at least one customer to import.',
            ],
            'connectorsPageUrl' => $canManageImports
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
            ], $this->availableSourcesForTenant((int) auth()->user()->tenant_id)),
            'rowBehavior' => [
                'hideDuplicatesByDefault' => false,
                'selectVisibleNonDuplicateRowsOnly' => false,
                'submitSelectedVisibleRowsOnly' => false,
                'duplicateFlagField' => 'is_duplicate',
                'selectionField' => 'selected',
            ],
            'previewDisplay' => [
                'titleExpression' => "row.name || '—'",
                'subtitleExpression' => "row.email || row.external_id || ''",
                'bodyExpression' => "[row.address_line_1, row.address_line_2, row.city, row.region, row.postal_code, row.country_code].filter(Boolean).join(', ') || '—'",
                'searchExpressions' => [
                    'row.name',
                    'row.email',
                    'row.phone',
                    'row.external_id',
                    'row.external_source',
                    'row.address_line_1',
                    'row.address_line_2',
                    'row.city',
                    'row.region',
                    'row.postal_code',
                    'row.country_code',
                    'previewStatusLabel(row)',
                ],
                'errorFields' => [
                    'name',
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
}
