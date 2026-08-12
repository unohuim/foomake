<?php

namespace App\Http\Controllers;

use App\Actions\Integrations\CreateEmptyFulfillmentRecipeForImportedItem;
use App\Http\Requests\Sales\ListSalesProductsRequest;
use App\Http\Requests\Sales\ImportExternalProductsRequest;
use App\Http\Requests\Sales\PreviewExternalProductImportRequest;
use App\Http\Requests\Sales\StoreSalesProductRequest;
use App\Integrations\WooCommerce\WooCommerceException;
use App\Models\ExternalProductSourceConnection;
use App\Models\Item;
use App\Models\Uom;
use App\Models\User;
use App\Navigation\NavigationEligibility;
use App\Services\WooCommerceProductPreviewService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Handle the sales-facing products index and ecommerce import workflow.
 */
class SalesProductController extends Controller
{
    /**
     * Display the sales products index.
     */
    public function index(Request $request, NavigationEligibility $navigationEligibility): Response
    {
        $this->authorizeIndex();

        $user = $request->user();

        $uoms = Uom::query()->orderBy('name')->get();
        $tenantCurrency = $user?->tenant?->currency_code ?: (string) config('app.currency_code', 'USD');
        $crudConfig = $this->productsCrudConfig();
        $importConfig = $this->productsImportConfig($user, $uoms);

        $payload = [
            'uoms' => $uoms->map(fn (Uom $uom): array => [
                'id' => $uom->id,
                'name' => $uom->name,
                'symbol' => $uom->symbol,
            ])->values()->all(),
            'sources' => $importConfig['sources'],
            'listUrl' => $crudConfig['endpoints']['list'],
            'storeUrl' => $crudConfig['endpoints']['create'],
            'updateUrlBase' => url('/sales/products'),
            'canManageImports' => $importConfig['permissions']['canManageImports'],
            'canManageProducts' => Gate::allows('inventory-products-manage'),
            'canManageConnections' => $importConfig['permissions']['canManageConnections'],
            'connectorsPageUrl' => $importConfig['connectorsPageUrl'],
            'connectUrlBase' => url('/sales/products/import-sources'),
            'previewUrl' => $importConfig['endpoints']['preview'],
            'importUrl' => $importConfig['endpoints']['store'],
            'navigationStateUrl' => route('navigation.state'),
            'csrfToken' => csrf_token(),
            'tenantCurrency' => Str::upper($tenantCurrency),
        ];

        return Inertia::render('Sales/Products/Index', [
            'shell' => $this->authShellPayload($request, $navigationEligibility),
            'crudConfig' => $crudConfig,
            'importConfig' => $importConfig,
            'payload' => $payload,
        ]);
    }

    /**
     * Return the Sales Products list read model for the page module.
     */
    public function list(ListSalesProductsRequest $request): JsonResponse
    {
        $this->authorizeIndex();

        $validated = $request->validated();
        $crudConfig = $this->productsCrudConfig();
        $search = trim((string) ($validated['search'] ?? ''));
        $sortColumn = (string) ($validated['sort'] ?? 'name');
        $direction = (string) ($validated['direction'] ?? 'desc');
        $allowedSortColumns = $crudConfig['sortable'];
        $products = $this->productsQuery($search, $sortColumn, $direction)->get();

        return response()->json([
            'data' => $products
                ->map(fn (Item $item): array => $this->productListData($item))
                ->values()
                ->all(),
            'meta' => [
                'search' => $search,
                'sort' => [
                    'column' => $sortColumn,
                    'direction' => $direction,
                ],
                'allowed_sort_columns' => $allowedSortColumns,
                'total' => $products->count(),
            ],
        ]);
    }

    /**
     * Download a CSV export for sales products.
     */
    public function export(ListSalesProductsRequest $request): StreamedResponse
    {
        $this->authorizeIndex();

        $validated = $request->validated();
        $scope = (string) ($validated['scope'] ?? 'current');
        $search = $scope === 'all'
            ? ''
            : trim((string) ($validated['search'] ?? ''));
        $sortColumn = $scope === 'all'
            ? 'name'
            : (string) ($validated['sort'] ?? 'name');
        $direction = $scope === 'all'
            ? 'desc'
            : (string) ($validated['direction'] ?? 'desc');
        $products = $this->productsQuery($search, $sortColumn, $direction)->get();

        return response()->streamDownload(function () use ($products): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $this->productExportHeaders());

            foreach ($products as $product) {
                fputcsv($handle, $this->productExportRow($product));
            }

            fclose($handle);
        }, 'products-export.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=products-export.csv',
        ]);
    }

    /**
     * Store a new product from the Sales Products create slide-over.
     */
    public function store(StoreSalesProductRequest $request): JsonResponse
    {
        $this->normalizePriceInput($request);

        $validated = $request->validated();
        $defaultPriceData = $this->resolveDefaultPriceData($request);

        $item = Item::query()->create(array_merge([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $validated['name'],
            'base_uom_id' => (int) $validated['base_uom_id'],
            'is_purchasable' => $request->boolean('is_purchasable'),
            'is_sellable' => true,
            'is_manufacturable' => $request->boolean('is_manufacturable'),
            'is_active' => true,
        ], $defaultPriceData));

        return response()->json([
            'data' => $this->productListData($item->load('baseUom')),
        ], 201);
    }

    /**
     * Update an existing sellable product from the Sales Products slide-over.
     */
    public function update(StoreSalesProductRequest $request, Item $item): JsonResponse
    {
        if (! $item->is_sellable) {
            throw new HttpException(404);
        }

        $this->normalizePriceInput($request);

        $validated = $request->validated();
        $defaultPriceData = $this->resolveDefaultPriceData($request);

        $item->update(array_merge([
            'name' => $validated['name'],
            'base_uom_id' => (int) $validated['base_uom_id'],
            'is_purchasable' => $request->boolean('is_purchasable'),
            'is_sellable' => true,
            'is_manufacturable' => $request->boolean('is_manufacturable'),
        ], $defaultPriceData));

        return response()->json([
            'data' => $this->productListData($item->fresh('baseUom')),
        ]);
    }

    /**
     * Return WooCommerce-backed preview rows for the selected source.
     */
    public function preview(
        PreviewExternalProductImportRequest $request,
        WooCommerceProductPreviewService $previewService
    ): JsonResponse {
        Gate::authorize('inventory-products-manage');

        $source = (string) $request->validated('source');
        $tenantId = (int) $request->user()->tenant_id;

        if ($source === 'file-upload') {
            return response()->json([
                'data' => [
                    'source' => $source,
                    'is_connected' => false,
                    'rows' => $this->annotateDuplicatePreviewRows(
                        $tenantId,
                        $request->validated('rows', []),
                        null
                    ),
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
                'message' => 'The external product preview could not be loaded.',
                'errors' => [
                    'source' => [$exception->getMessage()],
                ],
            ], 422);
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
     * Import selected preview rows as normal items.
     */
    public function storeImport(
        ImportExternalProductsRequest $request,
        CreateEmptyFulfillmentRecipeForImportedItem $createFulfillmentRecipeAction
    ): JsonResponse {
        Gate::authorize('inventory-products-manage');

        $source = $request->validated('source');
        $isLocalFileImport = $request->boolean('is_local_file_import');

        if (! $isLocalFileImport && ! $this->connectedSourceForTenant((int) $request->user()->tenant_id, (string) $source)) {
            return $this->notConnectedResponse();
        }

        $tenantId = (int) $request->user()->tenant_id;
        $createFulfillmentRecipes = $request->boolean('create_fulfillment_recipes', true);

        $summary = [
            'fulfillment_recipes_created' => 0,
            'fulfillment_recipes_skipped_existing' => 0,
            'fulfillment_recipes_not_attempted_existing_item' => 0,
        ];

        /** @var Collection<int, Item> $imported */
        $imported = collect();

        DB::transaction(function () use (
            $request,
            $source,
            $tenantId,
            $createFulfillmentRecipes,
            $createFulfillmentRecipeAction,
            &$summary,
            &$imported
        ): void {
            $imported = collect($request->validated('rows'))
                ->map(function (array $row) use (
                    $request,
                    $source,
                    $tenantId,
                    $createFulfillmentRecipes,
                    $createFulfillmentRecipeAction,
                    &$summary
                ): Item {
                    $item = $this->createOrUpdateImportedItem(
                        $request,
                        $source,
                        $tenantId,
                        $row
                    );

                    if (! $item->wasRecentlyCreated) {
                        $summary['fulfillment_recipes_not_attempted_existing_item']++;

                        return $item;
                    }

                    if (! $createFulfillmentRecipes) {
                        return $item;
                    }

                    if ($createFulfillmentRecipeAction->execute($item)) {
                        $summary['fulfillment_recipes_created']++;
                    } else {
                        $summary['fulfillment_recipes_skipped_existing']++;
                    }

                    return $item;
                });
        });

        return response()->json([
            'data' => [
                'imported_count' => $imported->count(),
                'fulfillment_recipes_created' => $summary['fulfillment_recipes_created'],
                'fulfillment_recipes_skipped_existing' => $summary['fulfillment_recipes_skipped_existing'],
                'fulfillment_recipes_not_attempted_existing_item' => $summary['fulfillment_recipes_not_attempted_existing_item'],
                'imported' => $imported
                    ->map(fn (Item $item): array => $this->productData($item->load('baseUom')))
                    ->values()
                    ->all(),
            ],
        ], 201);
    }

    /**
     * Create or update an imported item using the external identity as the tenant-scoped match key.
     *
     * @param array<string, mixed> $row
     */
    private function createOrUpdateImportedItem(
        ImportExternalProductsRequest $request,
        ?string $source,
        int $tenantId,
        array $row
    ): Item {
        $isManufacturable = array_key_exists('is_manufacturable', $row)
            ? (bool) $row['is_manufacturable']
            : $request->boolean('import_all_as_manufacturable');
        $isPurchasable = array_key_exists('is_purchasable', $row)
            ? (bool) $row['is_purchasable']
            : $request->boolean('import_all_as_purchasable');
        $baseUomId = array_key_exists('base_uom_id', $row) && $row['base_uom_id'] !== null
            ? (int) $row['base_uom_id']
            : (int) $request->validated('bulk_base_uom_id');
        $resolvedExternalSource = $this->resolvedRowExternalSource($source, $row);
        $resolvedExternalId = $this->normalizedExternalId($row['external_id'] ?? null) ?? '';
        $item = $this->findExistingImportedItem($tenantId, $resolvedExternalSource, $resolvedExternalId)
            ?? new Item([
                'tenant_id' => $tenantId,
                'external_source' => $resolvedExternalSource,
                'external_id' => $resolvedExternalId,
            ]);

        $item->name = $row['name'];
        $item->base_uom_id = $baseUomId;
        $item->is_active = array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true;
        $item->is_purchasable = $isPurchasable;
        $item->is_sellable = true;
        $item->is_manufacturable = $isManufacturable;

        if (array_key_exists('default_price_cents', $row)) {
            $defaultPriceCents = $this->resolvedImportedDefaultPriceCents($row);
            $item->default_price_cents = $defaultPriceCents;
            $item->default_price_currency_code = $defaultPriceCents === null
                ? null
                : $this->tenantCurrencyCodeForUser($request->user());
        }

        if (array_key_exists('image_url', $row)) {
            $item->image_url = $this->resolvedImportedImageUrl($row);
        }

        $item->save();

        return $item;
    }

    /**
     * Find an existing tenant-scoped imported item by normalized external identity.
     */
    private function findExistingImportedItem(int $tenantId, ?string $externalSource, string $externalId): ?Item
    {
        if ($externalSource === null || $externalId === '') {
            return null;
        }

        return Item::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(TRIM(external_source)) = ?', [$externalSource])
            ->whereRaw('TRIM(external_id) = ?', [$externalId])
            ->first();
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

        $existingKeys = Item::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('external_source')
            ->whereNotNull('external_id')
            ->get(['external_source', 'external_id'])
            ->map(fn (Item $item): string => $this->normalizedExternalSource($item->external_source) . '|' . $this->normalizedExternalId($item->external_id))
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
                    ? 'A product with the same external source and external ID already exists.'
                    : '',
                'selected' => ! $isDuplicate,
            ]);
        }, $rows));
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
     * Enforce products index access with the existing product permission slugs.
     */
    private function authorizeIndex(): void
    {
        if (Gate::allows('inventory-products-view') || Gate::allows('inventory-products-manage')) {
            return;
        }

        throw new HttpException(403);
    }

    /**
     * Build the shared authenticated Inertia shell payload.
     *
     * @return array<string, mixed>
     */
    private function authShellPayload(Request $request, NavigationEligibility $navigationEligibility): array
    {
        $user = $request->user();

        return [
            'logo' => [
                'src' => null,
                'alt' => config('app.name', 'Factory Manager'),
            ],
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'navigation' => [
                'dashboardUrl' => route('dashboard', absolute: false),
                'profileUrl' => route('profile.edit', absolute: false),
                'logoutUrl' => route('logout', absolute: false),
                'groups' => $this->navigationGroups($request, $user, $navigationEligibility->forUser($user)),
                'accountItems' => $this->accountNavigationItems($request, $user),
            ],
        ];
    }

    /**
     * Build the authenticated navigation groups for the Inertia auth shell.
     *
     * @param array<string, bool> $navigationEligibility
     * @return array<int, array<string, mixed>>
     */
    private function navigationGroups(Request $request, User $user, array $navigationEligibility): array
    {
        $groups = [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'active' => $request->routeIs('dashboard'),
                'items' => [
                    [
                        'label' => 'Dashboard',
                        'url' => route('dashboard', absolute: false),
                        'active' => $request->routeIs('dashboard'),
                        'enabled' => true,
                    ],
                ],
            ],
        ];

        $salesItems = array_values(array_filter([
            $user->can('sales-customers-manage') ? [
                'label' => 'Customers',
                'url' => route('sales.customers.index', absolute: false),
                'active' => $request->routeIs('sales.customers.*'),
                'enabled' => true,
            ] : null,
            ($user->can('inventory-products-view') || $user->can('inventory-products-manage')) ? [
                'label' => 'Products',
                'url' => route('sales.products.index', absolute: false),
                'active' => $request->routeIs('sales.products.*'),
                'enabled' => true,
            ] : null,
            $user->can('sales-sales-orders-manage') ? [
                'label' => 'Orders',
                'url' => route('sales.orders.index', absolute: false),
                'active' => $request->routeIs('sales.orders.*'),
                'enabled' => $navigationEligibility['salesOrdersEnabled'] ?? false,
                'disabledReason' => 'Add a customer and sellable product first.',
            ] : null,
        ]));

        if ($salesItems !== []) {
            $groups[] = [
                'key' => 'sales',
                'label' => 'Sales',
                'active' => $request->routeIs('sales.*'),
                'items' => $salesItems,
            ];
        }

        $purchasingItems = array_values(array_filter([
            $user->can('purchasing-purchase-orders-create') ? [
                'label' => 'Orders',
                'url' => route('purchasing.orders.index', absolute: false),
                'active' => $request->routeIs('purchasing.orders.*'),
                'enabled' => $navigationEligibility['purchaseOrdersEnabled'] ?? false,
                'disabledReason' => 'Add a supplier and purchasable material first.',
            ] : null,
            $user->can('purchasing-suppliers-view') ? [
                'label' => 'Suppliers',
                'url' => route('purchasing.suppliers.index', absolute: false),
                'active' => $request->routeIs('purchasing.suppliers.*'),
                'enabled' => true,
            ] : null,
        ]));

        if ($purchasingItems !== []) {
            $groups[] = [
                'key' => 'purchasing',
                'label' => 'Purchasing',
                'active' => $request->routeIs('purchasing.*'),
                'items' => $purchasingItems,
            ];
        }

        $manufacturingItems = array_values(array_filter([
            $user->can('inventory-make-orders-view') ? [
                'label' => 'Make Orders',
                'url' => route('manufacturing.make-orders.index', absolute: false),
                'active' => $request->routeIs('manufacturing.make-orders.*'),
                'enabled' => $navigationEligibility['makeOrdersEnabled'] ?? false,
                'disabledReason' => 'Add a manufacturable item and active recipe first.',
            ] : null,
            $user->can('inventory-recipes-view') ? [
                'label' => 'Recipes',
                'url' => route('manufacturing.recipes.index', absolute: false),
                'active' => $request->routeIs('manufacturing.recipes.*'),
                'enabled' => true,
            ] : null,
        ]));

        if ($manufacturingItems !== []) {
            $groups[] = [
                'key' => 'manufacturing',
                'label' => 'Manufacturing',
                'active' => $request->routeIs('manufacturing.make-orders.*') || $request->routeIs('manufacturing.recipes.*'),
                'items' => $manufacturingItems,
            ];
        }

        $stockItems = array_values(array_filter([
            ($user->can('inventory-adjustments-view') || $user->can('inventory-adjustments-execute')) ? [
                'label' => 'Inventory Counts',
                'url' => route('inventory.counts.index', absolute: false),
                'active' => $request->routeIs('inventory.counts.*'),
                'enabled' => true,
            ] : null,
            ($user->can('inventory-stock-view') || $user->can('inventory-materials-view') || $user->can('inventory-materials-manage')) ? [
                'label' => 'Materials',
                'url' => route('materials.index', absolute: false),
                'active' => $request->routeIs('materials.*') && ! $request->routeIs('materials.uom-categories.*'),
                'enabled' => true,
            ] : null,
            $user->can('inventory-materials-manage') ? [
                'label' => 'UoM',
                'active' => $request->routeIs('materials.uom-categories.*')
                    || $request->routeIs('manufacturing.uoms.*')
                    || $request->routeIs('manufacturing.uom-conversions.*'),
                'enabled' => true,
                'children' => [
                    [
                        'label' => 'UoM Categories',
                        'url' => route('materials.uom-categories.index', absolute: false),
                        'active' => $request->routeIs('materials.uom-categories.*'),
                        'enabled' => true,
                    ],
                    [
                        'label' => 'Units of Measure',
                        'url' => route('manufacturing.uoms.index', absolute: false),
                        'active' => $request->routeIs('manufacturing.uoms.*'),
                        'enabled' => true,
                    ],
                    [
                        'label' => 'UoM Conversions',
                        'url' => route('manufacturing.uom-conversions.index', absolute: false),
                        'active' => $request->routeIs('manufacturing.uom-conversions.*'),
                        'enabled' => true,
                    ],
                ],
            ] : null,
        ]));

        if ($stockItems !== []) {
            $groups[] = [
                'key' => 'stock',
                'label' => 'Stock',
                'active' => $request->routeIs('inventory.counts.*')
                    || $request->routeIs('materials.*')
                    || $request->routeIs('manufacturing.uoms.*')
                    || $request->routeIs('manufacturing.uom-conversions.*'),
                'items' => $stockItems,
            ];
        }

        return $groups;
    }

    /**
     * Build account links for the authenticated user menu.
     *
     * @return array<int, array<string, mixed>>
     */
    private function accountNavigationItems(Request $request, User $user): array
    {
        return array_values(array_filter([
            [
                'label' => 'Profile',
                'url' => route('profile.edit', absolute: false),
                'active' => $request->routeIs('profile.edit'),
                'enabled' => true,
            ],
            $user->can('billing-subscription-manage') ? [
                'label' => 'Billing',
                'url' => route('billing.index', absolute: false),
                'active' => $request->routeIs('billing.*'),
                'enabled' => true,
            ] : null,
            $user->can('system-users-manage') ? [
                'label' => 'Connectors',
                'url' => route('profile.connectors.index', absolute: false),
                'active' => $request->routeIs('profile.connectors.*'),
                'enabled' => true,
            ] : null,
            $user->can('workflow-manage') ? [
                'label' => 'Workflows',
                'url' => route('admin.workflows.index', absolute: false),
                'active' => $request->routeIs('admin.workflows.*'),
                'enabled' => true,
            ] : null,
            $user->can('admin-users-view') ? [
                'label' => 'Users',
                'url' => route('admin.users.index', absolute: false),
                'active' => $request->routeIs('admin.users.*'),
                'enabled' => true,
            ] : null,
        ]));
    }

    /**
     * Build the products index payload row.
     *
     * @return array<string, mixed>
     */
    private function productData(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'is_active' => $item->is_active,
            'status_label' => $item->is_active ? 'Active' : 'Inactive',
            'base_uom_name' => $item->baseUom?->name,
            'base_uom_symbol' => $item->baseUom?->symbol,
            'is_purchasable' => $item->is_purchasable,
            'is_sellable' => $item->is_sellable,
            'is_manufacturable' => $item->is_manufacturable,
            'external_source' => $item->external_source,
            'external_id' => $item->external_id,
        ];
    }

    /**
     * Build the JSON list row for the Sales Products desktop view.
     *
     * @return array<string, mixed>
     */
    private function productListData(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'base_uom' => [
                'id' => $item->baseUom?->id,
                'name' => $item->baseUom?->name,
                'symbol' => $item->baseUom?->symbol,
            ],
            'price' => $this->formatCentsToAmount($item->default_price_cents),
            'currency' => $item->default_price_currency_code,
            'image_url' => $item->image_url,
            'is_purchasable' => $item->is_purchasable,
            'is_manufacturable' => $item->is_manufacturable,
        ];
    }

    /**
     * Return the shared CRUD config for the Sales Products page module.
     *
     * @return array<string, mixed>
     */
    private function productsCrudConfig(): array
    {
        return [
            'resource' => 'products',
            'endpoints' => [
                'list' => route('sales.products.list'),
                'export' => route('sales.products.export'),
                'create' => route('sales.products.store'),
                'importPreview' => route('sales.products.import.preview'),
                'importStore' => route('sales.products.import.store'),
            ],
            'columns' => ['name', 'base_uom', 'price'],
            'headers' => [
                'name' => 'Name',
                'base_uom' => 'Base UoM',
                'price' => 'Price',
            ],
            'sortable' => ['name', 'base_uom', 'price'],
            'labels' => [
                'searchPlaceholder' => 'Search products',
                'exportTitle' => 'Export Products',
                'exportAriaLabel' => 'Export Products',
                'exportDescription' => 'Export products as CSV using the same import-relevant field set.',
                'exportFormatLabel' => 'CSV',
                'exportScopeLegend' => 'Export Scope',
                'exportCurrentOptionTitle' => 'Current filters and sort',
                'exportCurrentOptionDescription' => 'Uses the current search text and sort order from the products list.',
                'exportAllOptionTitle' => 'All records',
                'exportAllOptionDescription' => 'Exports every sellable product in the current tenant.',
                'exportCancelLabel' => 'Cancel',
                'exportSubmitLabel' => 'Export CSV',
                'exportUnavailableMessage' => 'Unable to export products.',
                'importTitle' => 'Import Products',
                'importAriaLabel' => 'Import Products',
                'createTitle' => 'Add New Product',
                'createAriaLabel' => 'Add New Product',
                'emptyState' => 'No products found.',
                'actionsAriaLabel' => 'Product actions',
            ],
            'permissions' => [
                'showExport' => Gate::allows('inventory-products-view') || Gate::allows('inventory-products-manage'),
                'showImport' => Gate::allows('inventory-products-manage'),
                'showCreate' => Gate::allows('inventory-products-manage'),
            ],
            'rowDisplay' => [
                'columns' => [
                    'name' => ['kind' => 'product-name'],
                    'base_uom' => ['kind' => 'text'],
                    'price' => ['kind' => 'text'],
                ],
            ],
            'mobileCard' => [
                'mediaExpression' => 'record.image_url',
                'titleExpression' => "record.name || '—'",
                'subtitleExpression' => 'productBaseUomLabel(record)',
                'bodyExpression' => 'formattedProductPrice(record)',
            ],
            'desktopCard' => [
                'titleExpression' => "record.name || '—'",
                'titleBadgesExpression' => 'productTitleBadges(record)',
                'subtitleExpression' => 'productBaseUomLabel(record)',
                'detailRowsExpression' => 'productCardRows(record)',
                'bodyExpression' => '',
                'showBody' => false,
                'compact' => true,
            ],
            'actions' => [
                [
                    'id' => 'edit',
                    'label' => 'Edit',
                    'tone' => 'default',
                ],
            ],
        ];
    }

    /**
     * Return the shared import config for the Sales Products import module.
     *
     * @return array<string, mixed>
     */
    private function productsImportConfig(\App\Models\User $user, Collection $uoms): array
    {
        $canManageImports = Gate::allows('inventory-products-manage');
        $canManageConnections = Gate::allows('system-users-manage');

        return [
            'resource' => 'products',
            'endpoints' => [
                'preview' => route('sales.products.import.preview'),
                'store' => route('sales.products.import.store'),
            ],
            'labels' => [
                'title' => 'Import Products',
                'source' => 'Ecommerce Store',
                'submit' => 'Import Selected',
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
                'canManageConnections' => $canManageConnections,
            ],
            'messages' => [
                'importUnavailable' => 'Unable to import products.',
                'emptyFileRows' => 'The selected CSV file does not contain any product rows.',
                'missingFileHeaders' => 'The selected CSV file is missing one or more required product headers.',
            ],
            'connectorsPageUrl' => $canManageConnections
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
            ], $this->availableSourcesForTenant((int) $user->tenant_id)),
            'uoms' => $uoms->map(fn (Uom $uom): array => [
                'id' => $uom->id,
                'name' => $uom->name,
                'symbol' => $uom->symbol,
            ])->values()->all(),
            'bulkOptions' => [
                'create_fulfillment_recipes' => [
                    'label' => 'Create fulfillment recipes',
                    'default' => true,
                ],
                'import_all_as_manufacturable' => [
                    'label' => 'Import all selected as manufacturable',
                    'default' => false,
                ],
                'import_all_as_purchasable' => [
                    'label' => 'Import all selected as buyable/purchasable',
                    'default' => false,
                ],
                'bulk_base_uom_id' => [
                    'label' => 'Bulk base UoM',
                    'default' => '',
                ],
            ],
            'rowBehavior' => [
                'hideDuplicatesByDefault' => false,
                'selectVisibleNonDuplicateRowsOnly' => true,
                'submitSelectedVisibleRowsOnly' => true,
                'duplicateFlagField' => 'is_duplicate',
                'selectionField' => 'selected',
            ],
            'previewDisplay' => [
                'titleExpression' => "row.name || '—'",
                'subtitleExpression' => "formattedProductPrice(row)",
                'bodyExpression' => '',
                'searchExpressions' => [
                    'row.name',
                    'row.sku',
                    'row.external_id',
                    'row.external_source',
                    'row.price',
                    'previewStatusLabel(row)',
                ],
                'errorFields' => [
                    'name',
                    'base_uom_id',
                ],
            ],
        ];
    }

    /**
     * Build the tenant-scoped sales products query used by list and export.
     */
    private function productsQuery(string $search, string $sortColumn, string $direction): Builder
    {
        $query = Item::query()
            ->with('baseUom')
            ->where('is_sellable', true);

        if ($search !== '') {
            $query->where('items.name', 'like', '%' . $search . '%');
        }

        match ($sortColumn) {
            'price' => $query->orderBy('items.default_price_cents', $direction)->orderBy('items.name'),
            'base_uom' => $query
                ->leftJoin('uoms', 'uoms.id', '=', 'items.base_uom_id')
                ->select('items.*')
                ->orderBy('uoms.name', $direction)
                ->orderBy('items.name'),
            default => $query->orderBy('items.name', $direction),
        };

        return $query;
    }

    /**
     * Return the CSV header row for product exports.
     *
     * @return array<int, string>
     */
    private function productExportHeaders(): array
    {
        return [
            'name',
            'base_uom_id',
            'is_active',
            'is_purchasable',
            'is_manufacturable',
            'default_price_amount',
            'default_price_currency_code',
            'external_source',
            'external_id',
        ];
    }

    /**
     * Return the CSV export row for a product.
     *
     * @return array<int, string>
     */
    private function productExportRow(Item $item): array
    {
        return [
            $item->name,
            (string) $item->base_uom_id,
            $item->is_active ? '1' : '0',
            $item->is_purchasable ? '1' : '0',
            $item->is_manufacturable ? '1' : '0',
            $this->formatCentsToAmount($item->default_price_cents) ?? '',
            $item->default_price_currency_code ?? '',
            $item->external_source ?? '',
            $item->external_id ?? '',
        ];
    }

    /**
     * Normalize nullable price inputs before validation.
     */
    private function normalizePriceInput(StoreSalesProductRequest $request): void
    {
        $payload = $request->all();

        if (array_key_exists('default_price_amount', $payload) && $payload['default_price_amount'] === '') {
            $request->merge(['default_price_amount' => null]);
        }

        if (array_key_exists('default_price_currency_code', $payload) && $payload['default_price_currency_code'] === '') {
            $request->merge(['default_price_currency_code' => null]);
        }
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
     * Normalize a numeric amount string to integer cents without float casting.
     */
    private function normalizeAmountToCents(string $amount): int
    {
        if (str_contains($amount, '.')) {
            [$whole, $decimal] = explode('.', $amount, 2);
            $decimal = str_pad(substr($decimal, 0, 2), 2, '0');

            return (((int) $whole) * 100) + ((int) $decimal);
        }

        return ((int) $amount) * 100;
    }

    /**
     * Resolve the imported default price cents from a row payload.
     *
     * @param  array<string, mixed>  $row
     */
    private function resolvedImportedDefaultPriceCents(array $row): ?int
    {
        if (! array_key_exists('default_price_cents', $row) || $row['default_price_cents'] === null || $row['default_price_cents'] === '') {
            return null;
        }

        return (int) $row['default_price_cents'];
    }

    /**
     * Resolve the imported image URL from a row payload.
     *
     * @param  array<string, mixed>  $row
     */
    private function resolvedImportedImageUrl(array $row): ?string
    {
        $imageUrl = trim((string) ($row['image_url'] ?? ''));

        return $imageUrl === '' ? null : $imageUrl;
    }

    /**
     * Resolve the tenant currency code used for imported planning prices.
     */
    private function tenantCurrencyCodeForUser(?\App\Models\User $user): string
    {
        return Str::upper((string) ($user?->tenant?->currency_code ?: config('app.currency_code', 'USD')));
    }

    /**
     * Resolve default price payload for a newly created product.
     *
     * @return array<string, int|string|null>
     */
    private function resolveDefaultPriceData(StoreSalesProductRequest $request): array
    {
        $amountValue = $request->input('default_price_amount');
        $currencyValue = $request->input('default_price_currency_code');

        if ($amountValue === null || $amountValue === '') {
            return [
                'default_price_cents' => null,
                'default_price_currency_code' => null,
            ];
        }

        $currencyCode = $currencyValue
            ? Str::upper((string) $currencyValue)
            : Str::upper((string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD')));

        return [
            'default_price_cents' => $this->normalizeAmountToCents((string) $amountValue),
            'default_price_currency_code' => $currencyCode,
        ];
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
