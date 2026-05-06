<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sales\ConnectExternalProductSourceRequest;
use App\Http\Requests\Sales\ImportExternalProductsRequest;
use App\Http\Requests\Sales\PreviewExternalProductImportRequest;
use App\Models\Item;
use App\Models\Uom;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Handle the sales-facing products index and stub import workflow.
 */
class SalesProductController extends Controller
{
    /**
     * Display the sales products index.
     */
    public function index(): View
    {
        $this->authorizeIndex();

        $canManageImports = Gate::allows('inventory-products-manage');
        $products = Item::query()
            ->with('baseUom')
            ->where('is_sellable', true)
            ->orderBy('name')
            ->get();
        $uoms = Uom::query()->orderBy('name')->get();
        $connectedSources = $this->connectedSourcesForTenant((int) auth()->user()->tenant_id);

        $payload = [
            'products' => $products->map(fn (Item $item): array => $this->productData($item))->values()->all(),
            'uoms' => $uoms->map(fn (Uom $uom): array => [
                'id' => $uom->id,
                'name' => $uom->name,
                'symbol' => $uom->symbol,
            ])->values()->all(),
            'sources' => $this->availableSources($connectedSources),
            'canManageImports' => $canManageImports,
            'connectUrlBase' => url('/sales/products/import-sources'),
            'previewUrl' => route('sales.products.import.preview'),
            'importUrl' => route('sales.products.import.store'),
            'navigationStateUrl' => route('navigation.state'),
            'csrfToken' => csrf_token(),
        ];

        return view('sales.products.index', [
            'payload' => $payload,
        ]);
    }

    /**
     * Store a temporary, prep-only connection state for a source.
     */
    public function connect(ConnectExternalProductSourceRequest $request, string $source): JsonResponse
    {
        Gate::authorize('inventory-products-manage');

        DB::table('external_product_source_connections')->updateOrInsert(
            [
                'tenant_id' => $request->user()->tenant_id,
                'source' => $source,
            ],
            [
                'connection_label' => $request->validated('connection_label'),
                'is_connected' => true,
                'connected_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $connection = DB::table('external_product_source_connections')
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('source', $source)
            ->first();

        return response()->json([
            'data' => [
                'source' => $source,
                'is_connected' => (bool) ($connection->is_connected ?? false),
                'connection_label' => $connection->connection_label,
                'connected_at' => $connection->connected_at,
            ],
        ], 201);
    }

    /**
     * Return deterministic preview rows for the selected source.
     */
    public function preview(PreviewExternalProductImportRequest $request): JsonResponse
    {
        Gate::authorize('inventory-products-manage');

        $source = (string) $request->validated('source');

        if (! $this->sourceIsConnected((int) $request->user()->tenant_id, $source)) {
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

        $defaultUomId = Uom::query()->orderBy('name')->value('id');

        return response()->json([
            'data' => [
                'source' => $source,
                'is_connected' => true,
                'rows' => $this->stubPreviewRows($source, $defaultUomId),
            ],
        ]);
    }

    /**
     * Import selected preview rows as normal items.
     */
    public function storeImport(ImportExternalProductsRequest $request): JsonResponse
    {
        Gate::authorize('inventory-products-manage');

        $source = (string) $request->validated('source');

        if (! $this->sourceIsConnected((int) $request->user()->tenant_id, $source)) {
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

        $imported = collect();

        try {
            DB::transaction(function () use ($request, $source, &$imported): void {
                $imported = collect($request->validated('rows'))
                    ->map(function (array $row) use ($request, $source): Item {
                        $isManufacturable = array_key_exists('is_manufacturable', $row)
                            ? (bool) $row['is_manufacturable']
                            : $request->boolean('import_all_as_manufacturable');
                        $isPurchasable = array_key_exists('is_purchasable', $row)
                            ? (bool) $row['is_purchasable']
                            : $request->boolean('import_all_as_purchasable');

                        return Item::query()->create([
                            'tenant_id' => $request->user()->tenant_id,
                            'name' => $row['name'],
                            'base_uom_id' => (int) $row['base_uom_id'],
                            'is_active' => array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true,
                            'is_purchasable' => $isPurchasable,
                            'is_sellable' => true,
                            'is_manufacturable' => $isManufacturable,
                            'default_price_cents' => null,
                            'default_price_currency_code' => null,
                            'external_source' => $source,
                            'external_id' => (string) $row['external_id'],
                        ]);
                    });
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateExternalIdentityException($exception)) {
                return response()->json([
                    'message' => 'One or more imported products already exist for this tenant and source.',
                    'errors' => [
                        'rows' => ['Duplicate source and external_id values are not allowed within the same tenant.'],
                    ],
                ], 422);
            }

            throw $exception;
        }

        return response()->json([
            'data' => [
                'imported_count' => $imported->count(),
                'imported' => $imported->map(fn (Item $item): array => $this->productData($item->load('baseUom')))->values()->all(),
            ],
        ], 201);
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
     * Return the currently connected sources for a tenant.
     *
     * @return array<int, string>
     */
    private function connectedSourcesForTenant(int $tenantId): array
    {
        return DB::table('external_product_source_connections')
            ->where('tenant_id', $tenantId)
            ->where('is_connected', true)
            ->pluck('source')
            ->all();
    }

    /**
     * Return the available import sources and their temporary connection status.
     *
     * @param array<int, string> $connectedSources
     * @return array<int, array<string, mixed>>
     */
    private function availableSources(array $connectedSources): array
    {
        return [
            [
                'value' => 'woocommerce',
                'label' => 'WooCommerce',
                'enabled' => true,
                'connected' => in_array('woocommerce', $connectedSources, true),
            ],
            [
                'value' => 'shopify',
                'label' => 'Shopify',
                'enabled' => false,
                'connected' => in_array('shopify', $connectedSources, true),
            ],
        ];
    }

    /**
     * Determine whether the source is currently connected for the tenant.
     */
    private function sourceIsConnected(int $tenantId, string $source): bool
    {
        return DB::table('external_product_source_connections')
            ->where('tenant_id', $tenantId)
            ->where('source', $source)
            ->where('is_connected', true)
            ->exists();
    }

    /**
     * Return deterministic preview rows for the prep-only import contract.
     *
     * @return array<int, array<string, mixed>>
     */
    private function stubPreviewRows(string $source, ?int $defaultUomId): array
    {
        if ($source === 'shopify') {
            return [];
        }

        return [
            [
                'external_id' => 'woo-stub-1001',
                'sku' => 'WC-STUB-1001',
                'name' => 'Woo Stub Product 1001',
                'is_active' => true,
                'is_sellable' => true,
                'is_manufacturable' => false,
                'is_purchasable' => false,
                'base_uom_id' => $defaultUomId,
            ],
            [
                'external_id' => 'woo-stub-1002',
                'sku' => 'WC-STUB-1002',
                'name' => 'Woo Stub Product 1002',
                'is_active' => false,
                'is_sellable' => true,
                'is_manufacturable' => false,
                'is_purchasable' => true,
                'base_uom_id' => $defaultUomId,
            ],
            [
                'external_id' => 'woo-stub-1003',
                'sku' => 'WC-STUB-1003',
                'name' => 'Woo Stub Product 1003',
                'is_active' => true,
                'is_sellable' => true,
                'is_manufacturable' => true,
                'is_purchasable' => false,
                'base_uom_id' => $defaultUomId,
            ],
        ];
    }

    /**
     * Detect duplicate external identity violations from the database layer.
     */
    private function isDuplicateExternalIdentityException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'items_tenant_source_external_unique')
            || str_contains($message, 'duplicate')
            || str_contains($message, 'unique');
    }
}
