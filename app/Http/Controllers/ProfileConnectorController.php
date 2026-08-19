<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\StoreWooCommerceConnectionRequest;
use App\Integrations\GoogleSearchConsole\GoogleSearchConsoleAdapter;
use App\Integrations\GoogleSearchConsole\GoogleSearchConsoleException;
use App\Integrations\WooCommerce\WooCommerceException;
use App\Models\ExternalProductSourceConnection;
use App\Models\GoogleSearchConsoleConnection;
use App\Models\User;
use App\Models\WordPressPluginConnection;
use App\Models\WordPressPluginPairingCode;
use App\Navigation\NavigationEligibility;
use App\Services\GoogleSearchConsoleReportService;
use App\Services\WooCommerceProductPreviewService;
use App\Support\Integrations\WordPressPluginArchive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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
        $pluginConnection = $this->currentWordPressPluginConnection((int) $user->tenant_id);
        $googleSearchConsoleConnection = GoogleSearchConsoleConnection::query()
            ->where('tenant_id', $user->tenant_id)
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
                'googleSearchConsole' => $this->googleSearchConsoleConnectionData($googleSearchConsoleConnection),
                'googleSearchConsoleConnectUrl' => route('profile.connectors.google-search-console.connect'),
                'googleSearchConsoleDisconnectUrl' => route('profile.connectors.google-search-console.destroy'),
                'googleSearchConsolePerformanceUrl' => route('profile.connectors.google-search-console.performance'),
                'googleSearchConsoleReportUrl' => route('profile.connectors.google-search-console.report'),
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
                'site_access_token' => null,
                'revoked_at' => now(),
            ])->save();
        }

        return response()->json([
            'data' => $this->wordPressPluginConnectionData($connection?->fresh()),
        ]);
    }

    /**
     * Redirect the tenant admin to Google for Search Console consent.
     */
    public function connectGoogleSearchConsole(Request $request, GoogleSearchConsoleAdapter $client): RedirectResponse
    {
        Gate::authorize('system-users-manage');

        /** @var \App\Models\User $user */
        $user = $request->user();
        $state = Str::random(48);

        $request->session()->put('google_search_console_oauth_state', [
            'state' => $state,
            'tenant_id' => $user->tenant_id,
        ]);

        try {
            $authorizationUrl = $client->authorizationUrl(
                $state,
                $this->googleSearchConsoleRedirectUri()
            );
        } catch (GoogleSearchConsoleException $exception) {
            $request->session()->forget('google_search_console_oauth_state');

            return redirect()
                ->route('profile.connectors.index')
                ->withErrors(['google_search_console' => $exception->getMessage()]);
        }

        return redirect()->away($authorizationUrl);
    }

    /**
     * Complete Google OAuth and store tenant-scoped Search Console credentials.
     */
    public function callbackGoogleSearchConsole(Request $request, GoogleSearchConsoleAdapter $client): RedirectResponse
    {
        Gate::authorize('system-users-manage');

        /** @var \App\Models\User $user */
        $user = $request->user();
        $state = $request->session()->pull('google_search_console_oauth_state');

        if (
            ! is_array($state)
            || ! hash_equals((string) ($state['state'] ?? ''), (string) $request->query('state', ''))
            || (int) ($state['tenant_id'] ?? 0) !== (int) $user->tenant_id
        ) {
            return redirect()
                ->route('profile.connectors.index')
                ->withErrors(['google_search_console' => 'Google Search Console authorization could not be verified.']);
        }

        if ($request->filled('error')) {
            return redirect()
                ->route('profile.connectors.index')
                ->withErrors(['google_search_console' => 'Google Search Console authorization was not approved.']);
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return redirect()
                ->route('profile.connectors.index')
                ->withErrors(['google_search_console' => 'Google did not return an authorization code.']);
        }

        try {
            $tokens = $client->exchangeCode($code, $this->googleSearchConsoleRedirectUri());
        } catch (GoogleSearchConsoleException $exception) {
            return redirect()
                ->route('profile.connectors.index')
                ->withErrors(['google_search_console' => $exception->getMessage()]);
        }

        $existing = GoogleSearchConsoleConnection::query()
            ->where('tenant_id', $user->tenant_id)
            ->first();
        $refreshToken = (string) ($tokens['refresh_token'] ?? $existing?->refresh_token ?? '');

        if ($refreshToken === '') {
            return redirect()
                ->route('profile.connectors.index')
                ->withErrors(['google_search_console' => 'Google did not return offline access. Reconnect and approve offline access.']);
        }

        $connection = GoogleSearchConsoleConnection::query()->updateOrCreate(
            ['tenant_id' => $user->tenant_id],
            [
                'site_url' => $existing?->site_url ?: config('services.google_search_console.site_url'),
                'scopes' => $this->googleSearchConsoleScopes($tokens),
                'access_token' => $tokens['access_token'] ?? null,
                'refresh_token' => $refreshToken,
                'token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
                'status' => GoogleSearchConsoleConnection::STATUS_CONNECTED,
                'connected_at' => now(),
                'last_verified_at' => now(),
                'last_error' => null,
            ]
        );

        try {
            $siteUrl = $connection->site_url ?: $this->selectGoogleSearchConsoleSite($client->sites($connection));

            if ($siteUrl !== null) {
                $connection->forceFill([
                    'site_url' => $siteUrl,
                    'last_verified_at' => now(),
                    'last_error' => null,
                ])->save();
            }
        } catch (GoogleSearchConsoleException $exception) {
            $connection->forceFill([
                'last_error' => $exception->getMessage(),
            ])->save();
        }

        return redirect()->route('profile.connectors.index');
    }

    /**
     * Disconnect Search Console while preserving the tenant row.
     */
    public function destroyGoogleSearchConsole(): JsonResponse
    {
        Gate::authorize('system-users-manage');

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $connection = GoogleSearchConsoleConnection::query()->firstOrNew([
            'tenant_id' => $user->tenant_id,
        ]);

        if (! $connection->exists) {
            $connection->tenant_id = $user->tenant_id;
            $connection->scopes = [];
        }

        $connection->fill([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'status' => GoogleSearchConsoleConnection::STATUS_DISCONNECTED,
            'connected_at' => null,
            'last_error' => null,
        ]);
        $connection->save();

        return response()->json([
            'data' => $this->googleSearchConsoleConnectionData($connection),
        ]);
    }

    /**
     * Return a short Search Console performance preview for the connected tenant.
     */
    public function googleSearchConsolePerformance(Request $request, GoogleSearchConsoleAdapter $client): JsonResponse
    {
        Gate::authorize('system-users-manage');

        /** @var \App\Models\User $user */
        $user = $request->user();
        $connection = GoogleSearchConsoleConnection::query()
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (! $connection?->isConnected()) {
            return response()->json([
                'message' => 'Google Search Console is not connected.',
            ], 409);
        }

        $days = max(1, min(90, (int) $request->integer('days', 28)));

        try {
            $performance = $client->searchAnalytics(
                $connection,
                now()->subDays($days),
                now()->subDay(),
                ['query'],
                10
            );
        } catch (GoogleSearchConsoleException $exception) {
            $connection->forceFill([
                'last_error' => $exception->getMessage(),
            ])->save();

            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $connection->forceFill([
            'last_verified_at' => now(),
            'last_error' => null,
        ])->save();

        return response()->json([
            'data' => [
                'site_url' => $connection->site_url,
                'rows' => collect($performance['rows'] ?? [])
                    ->map(fn (array $row): array => [
                        'query' => (string) data_get($row, 'keys.0', ''),
                        'clicks' => (int) ($row['clicks'] ?? 0),
                        'impressions' => (int) ($row['impressions'] ?? 0),
                        'ctr' => (float) ($row['ctr'] ?? 0),
                        'position' => (float) ($row['position'] ?? 0),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * Download the connected Search Console report as markdown.
     */
    public function downloadGoogleSearchConsoleReport(
        Request $request,
        GoogleSearchConsoleReportService $reports
    ): SymfonyResponse {
        Gate::authorize('system-users-manage');

        /** @var \App\Models\User $user */
        $user = $request->user();
        $connection = GoogleSearchConsoleConnection::query()
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (! $connection?->isConnected()) {
            abort(409, 'Google Search Console is not connected.');
        }

        return response($reports->markdown($connection, now()), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="search_console_report.md"',
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
     * Build the safe Google Search Console connection payload returned to the UI.
     *
     * @return array<string, mixed>
     */
    private function googleSearchConsoleConnectionData(?GoogleSearchConsoleConnection $connection): array
    {
        return [
            'status' => $connection?->status ?? GoogleSearchConsoleConnection::STATUS_DISCONNECTED,
            'connected' => $connection?->isConnected() ?? false,
            'site_url' => $connection?->site_url,
            'last_verified_at' => $connection?->last_verified_at?->toAtomString(),
            'last_error' => $connection?->last_error,
        ];
    }

    /**
     * Extract the granted OAuth scopes from Google's token response.
     *
     * @param array<string, mixed> $tokens
     * @return list<string>
     */
    private function googleSearchConsoleScopes(array $tokens): array
    {
        $scope = (string) ($tokens['scope'] ?? GoogleSearchConsoleAdapter::READONLY_SCOPE);

        return array_values(array_filter(explode(' ', $scope)));
    }

    /**
     * Return the exact OAuth callback URI registered with Google.
     */
    private function googleSearchConsoleRedirectUri(): string
    {
        $configured = config('services.google_search_console.redirect_uri');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if (app()->environment('local')) {
            return 'http://localhost:8000/integrations/google/search-console/callback';
        }

        return route('profile.connectors.google-search-console.callback');
    }

    /**
     * Select the first verified Search Console site when no property is configured.
     *
     * @param array<int, array<string, mixed>> $sites
     */
    private function selectGoogleSearchConsoleSite(array $sites): ?string
    {
        foreach ($sites as $site) {
            $permissionLevel = (string) ($site['permissionLevel'] ?? '');

            if ($permissionLevel !== 'siteUnverifiedUser' && filled($site['siteUrl'] ?? null)) {
                return (string) $site['siteUrl'];
            }
        }

        return null;
    }

    /**
     * Return the current WordPress plugin connection, preferring active pairings.
     */
    private function currentWordPressPluginConnection(int $tenantId): ?WordPressPluginConnection
    {
        /** @var WordPressPluginConnection|null $connected */
        $connected = WordPressPluginConnection::query()
            ->where('tenant_id', $tenantId)
            ->where('status', WordPressPluginConnection::STATUS_CONNECTED)
            ->whereNull('revoked_at')
            ->latest('last_seen_at')
            ->latest('connected_at')
            ->latest('id')
            ->first();

        if ($connected?->isConnected()) {
            return $connected;
        }

        /** @var WordPressPluginConnection|null $latest */
        $latest = WordPressPluginConnection::query()
            ->where('tenant_id', $tenantId)
            ->latest('connected_at')
            ->latest('id')
            ->first();

        return $latest;
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
