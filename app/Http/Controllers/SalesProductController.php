<?php

namespace App\Http\Controllers;

use App\Actions\Integrations\CreateEmptyFulfillmentRecipeForImportedItem;
use App\Http\Requests\Sales\ImportExternalProductsRequest;
use App\Http\Requests\Sales\PreviewExternalProductImportRequest;
use App\Integrations\WooCommerce\WooCommerceException;
use App\Models\ExternalProductSourceConnection;
use App\Models\Item;
use App\Models\Uom;
use App\Services\WooCommerceProductPreviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Handle the sales-facing products index and ecommerce import workflow.
 */
class SalesProductController extends Controller
{
    /**
     * Display the sales products index.
     */
    public function index(): View
    {
        $this->authorizeIndex();

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $products = Item::query()
            ->with('baseUom')
            ->where('is_sellable', true)
            ->orderBy('name')
            ->get();
        $uoms = Uom::query()->orderBy('name')->get();

        $payload = [
            'products' => $products->map(fn (Item $item): array => $this->productData($item))->values()->all(),
            'uoms' => $uoms->map(fn (Uom $uom): array => [
                'id' => $uom->id,
                'name' => $uom->name,
                'symbol' => $uom->symbol,
            ])->values()->all(),
            'sources' => $this->availableSourcesForTenant((int) $user->tenant_id),
            'canManageImports' => Gate::allows('inventory-products-manage'),
            'canManageConnections' => Gate::allows('system-users-manage'),
            'connectorsPageUrl' => Gate::allows('system-users-manage')
                ? route('profile.connectors.index')
                : null,
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
     * Return WooCommerce-backed preview rows for the selected source.
     */
    public function preview(
        PreviewExternalProductImportRequest $request,
        WooCommerceProductPreviewService $previewService
    ): JsonResponse {
        Gate::authorize('inventory-products-manage');

        $source = (string) $request->validated('source');
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
                'rows' => $rows,
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

        $source = (string) $request->validated('source');

        if (! $this->connectedSourceForTenant((int) $request->user()->tenant_id, $source)) {
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
        string $source,
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

        $item = Item::query()
            ->where('tenant_id', $tenantId)
            ->where('external_source', $source)
            ->where('external_id', (string) $row['external_id'])
            ->first();

        if (! $item) {
            $item = new Item([
                'tenant_id' => $tenantId,
                'external_source' => $source,
                'external_id' => (string) $row['external_id'],
            ]);
        }

        $item->name = $row['name'];
        $item->base_uom_id = $baseUomId;
        $item->is_active = array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true;
        $item->is_purchasable = $isPurchasable;
        $item->is_sellable = true;
        $item->is_manufacturable = $isManufacturable;
        $item->default_price_cents = null;
        $item->default_price_currency_code = null;
        $item->save();

        return $item;
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
}
