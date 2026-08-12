<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\StoreWooCommerceConnectionRequest;
use App\Integrations\WooCommerce\WooCommerceException;
use App\Models\ExternalProductSourceConnection;
use App\Models\User;
use App\Models\WordPressPluginConnection;
use App\Models\WordPressPluginPairingCode;
use App\Navigation\NavigationEligibility;
use App\Services\WooCommerceProductPreviewService;
use App\Support\Integrations\WordPressPluginArchive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Manage tenant connector credentials from the authenticated profile area.
 */
class ProfileConnectorController extends Controller
{
    /**
     * Display the connector management page.
     */
    public function index(Request $request, NavigationEligibility $navigationEligibility): Response
    {
        Gate::authorize('system-users-manage');

        /** @var \App\Models\User $user */
        $user = $request->user();
        $connection = ExternalProductSourceConnection::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('source', ExternalProductSourceConnection::SOURCE_WOOCOMMERCE)
            ->first();
        $pluginConnection = WordPressPluginConnection::query()
            ->where('tenant_id', $user->tenant_id)
            ->latest('connected_at')
            ->latest('id')
            ->first();

        return Inertia::render('Profile/Connectors/Index', [
            'shell' => [
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
            ],
            'connectors' => [
                'wooCommerce' => $this->connectionData($connection),
                'wordPressPlugin' => $this->wordPressPluginConnectionData($pluginConnection),
                'storeUrl' => route('profile.connectors.woocommerce.store'),
                'disconnectUrl' => route('profile.connectors.woocommerce.destroy'),
                'pluginDownloadUrl' => route('profile.connectors.woocommerce.plugin.download'),
                'pluginRevokeUrl' => route('profile.connectors.wordpress-plugin.destroy'),
                'csrfToken' => csrf_token(),
            ],
        ]);
    }

    /**
     * Download the FooMake WordPress connector plugin archive.
     */
    public function downloadWooCommercePlugin(WordPressPluginArchive $archive): BinaryFileResponse
    {
        Gate::authorize('system-users-manage');

        try {
            $archivePath = $archive->build();
        } catch (RuntimeException $exception) {
            abort(503, $exception->getMessage());
        }

        return response()
            ->download($archivePath, 'foomake-connector.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    /**
     * Display a pending WordPress plugin pairing approval page.
     */
    public function showWordPressPairing(Request $request, NavigationEligibility $navigationEligibility): Response
    {
        Gate::authorize('system-users-manage');

        /** @var \App\Models\User $user */
        $user = $request->user();
        $code = (string) $request->query('code', '');
        $pairing = $this->pairingForCode($code);
        $error = $this->pairingError($pairing);

        return Inertia::render('Profile/Connectors/WordPressPair', [
            'shell' => [
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
            ],
            'pairing' => [
                'code' => $error === null ? $code : '',
                'siteUrl' => $pairing?->site_url,
                'siteName' => $pairing?->site_name,
                'expiresAt' => $pairing?->expires_at?->toAtomString(),
                'approveUrl' => route('profile.connectors.wordpress.pair.approve'),
                'connectorsUrl' => route('profile.connectors.index', absolute: false),
                'csrfToken' => csrf_token(),
                'canApprove' => $error === null,
                'error' => $error,
            ],
        ]);
    }

    /**
     * Approve a WordPress plugin pairing request for the current tenant.
     */
    public function approveWordPressPairing(Request $request): RedirectResponse
    {
        Gate::authorize('system-users-manage');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:128'],
        ]);

        $pairing = $this->pairingForCode($validated['code']);
        $error = $this->pairingError($pairing);

        if ($error !== null) {
            return redirect()
                ->route('profile.connectors.wordpress.pair', ['code' => $validated['code']])
                ->withErrors(['code' => $error]);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $pairing->forceFill([
            'tenant_id' => $user->tenant_id,
            'approved_at' => now(),
        ])->save();

        return redirect()->away($this->callbackUrlWithCode($pairing->callback_url, $validated['code']));
    }

    /**
     * Revoke the current tenant WordPress plugin connection.
     */
    public function destroyWordPressPluginConnection(): JsonResponse
    {
        Gate::authorize('system-users-manage');

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $connection = WordPressPluginConnection::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('status', WordPressPluginConnection::STATUS_CONNECTED)
            ->latest('connected_at')
            ->latest('id')
            ->first();

        if ($connection) {
            $connection->forceFill([
                'status' => WordPressPluginConnection::STATUS_REVOKED,
                'access_token_hash' => null,
                'revoked_at' => now(),
            ])->save();
        }

        return response()->json([
            'data' => $this->wordPressPluginConnectionData($connection?->fresh()),
        ]);
    }

    /**
     * Verify and save WooCommerce credentials for the current tenant.
     */
    public function storeWooCommerce(
        StoreWooCommerceConnectionRequest $request,
        WooCommerceProductPreviewService $previewService
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $previewService->verifyCredentials(
                $validated['store_url'],
                $validated['consumer_key'],
                $validated['consumer_secret']
            );
        } catch (WooCommerceException $exception) {
            return response()->json([
                'message' => 'The WooCommerce connection could not be verified.',
                'errors' => [
                    'store_url' => [$exception->getMessage()],
                ],
            ], 422);
        }

        $connection = ExternalProductSourceConnection::query()->updateOrCreate(
            [
                'tenant_id' => $request->user()->tenant_id,
                'source' => ExternalProductSourceConnection::SOURCE_WOOCOMMERCE,
            ],
            [
                'store_url' => $validated['store_url'],
                'consumer_key' => $validated['consumer_key'],
                'consumer_secret' => $validated['consumer_secret'],
                'status' => ExternalProductSourceConnection::STATUS_CONNECTED,
                'is_connected' => true,
                'connected_at' => now(),
                'last_verified_at' => now(),
                'last_error' => null,
            ]
        );

        return response()->json([
            'data' => $this->connectionData($connection),
        ]);
    }

    /**
     * Disconnect WooCommerce while preserving the tenant record.
     */
    public function destroyWooCommerce(): JsonResponse
    {
        Gate::authorize('system-users-manage');

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $connection = ExternalProductSourceConnection::query()->firstOrNew([
            'tenant_id' => $user->tenant_id,
            'source' => ExternalProductSourceConnection::SOURCE_WOOCOMMERCE,
        ]);

        if (! $connection->exists) {
            $connection->tenant_id = $user->tenant_id;
            $connection->source = ExternalProductSourceConnection::SOURCE_WOOCOMMERCE;
        }

        $connection->fill([
            'store_url' => null,
            'consumer_key' => null,
            'consumer_secret' => null,
            'status' => ExternalProductSourceConnection::STATUS_DISCONNECTED,
            'is_connected' => false,
            'connected_at' => null,
            'last_error' => null,
        ]);
        $connection->save();

        return response()->json([
            'data' => $this->connectionData($connection),
        ]);
    }

    /**
     * Build the safe connection payload returned to the UI.
     *
     * @return array<string, mixed>
     */
    private function connectionData(?ExternalProductSourceConnection $connection): array
    {
        return [
            'source' => ExternalProductSourceConnection::SOURCE_WOOCOMMERCE,
            'status' => $connection?->status ?? ExternalProductSourceConnection::STATUS_DISCONNECTED,
            'connected' => $connection?->isConnected() ?? false,
            'is_connected' => $connection?->isConnected() ?? false,
            'last_verified_at' => $connection?->last_verified_at?->toAtomString(),
            'last_error' => $connection?->last_error,
        ];
    }

    /**
     * Build the safe WordPress plugin connection payload returned to the UI.
     *
     * @return array<string, mixed>
     */
    private function wordPressPluginConnectionData(?WordPressPluginConnection $connection): array
    {
        return [
            'status' => $connection?->status ?? WordPressPluginConnection::STATUS_REVOKED,
            'connected' => $connection?->isConnected() ?? false,
            'site_url' => $connection?->site_url,
            'site_name' => $connection?->site_name,
            'last_seen_at' => $connection?->last_seen_at?->toAtomString(),
            'connected_at' => $connection?->connected_at?->toAtomString(),
            'revoked_at' => $connection?->revoked_at?->toAtomString(),
        ];
    }

    /**
     * Find a pairing request from a raw code.
     */
    private function pairingForCode(string $code): ?WordPressPluginPairingCode
    {
        if (trim($code) === '') {
            return null;
        }

        return WordPressPluginPairingCode::query()
            ->where('code_hash', WordPressPluginPairingCode::hashCode($code))
            ->first();
    }

    /**
     * Return a safe display error when a pairing request cannot be approved.
     */
    private function pairingError(?WordPressPluginPairingCode $pairing): ?string
    {
        if (! $pairing) {
            return 'The WordPress plugin pairing link is invalid.';
        }

        if ($pairing->isExpired()) {
            return 'The WordPress plugin pairing link has expired.';
        }

        if ($pairing->isConsumed()) {
            return 'The WordPress plugin pairing link has already been used.';
        }

        return null;
    }

    /**
     * Append the raw pairing code to the plugin callback URL.
     */
    private function callbackUrlWithCode(string $callbackUrl, string $code): string
    {
        $separator = str_contains($callbackUrl, '?') ? '&' : '?';

        return $callbackUrl . $separator . http_build_query(['code' => $code]);
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
}
