<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Integrations\GoogleSearchConsole\GoogleSearchConsoleAdapter;
use App\Integrations\GoogleSearchConsole\GoogleSearchConsoleException;
use App\Models\GoogleSearchConsoleConnection;
use App\Models\User;
use App\Navigation\NavigationEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Display super-admin marketing tools.
 */
class MarketingController extends Controller
{
    /**
     * Show the marketing operations page.
     */
    public function __invoke(Request $request, NavigationEligibility $navigationEligibility): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasRole('super-admin'), 403);

        $connection = GoogleSearchConsoleConnection::query()
            ->where('tenant_id', $user->tenant_id)
            ->first();

        return Inertia::render('Admin/Marketing', [
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
            'searchConsole' => [
                'connected' => $connection?->isConnected() ?? false,
                'siteUrl' => $connection?->site_url,
                'lastVerifiedAt' => $connection?->last_verified_at?->toAtomString(),
                'lastError' => $connection?->last_error,
                'connectorsUrl' => route('profile.connectors.index', absolute: false),
                'reportUrl' => route('profile.connectors.google-search-console.report', absolute: false),
                'dataUrl' => route('admin.marketing.search-console.data', absolute: false),
                'views' => $this->searchConsoleViews(),
                'timeframes' => $this->searchConsoleTimeframes(),
            ],
        ]);
    }

    /**
     * Return Search Console rows for the selected marketing view.
     */
    public function searchConsoleData(Request $request, GoogleSearchConsoleAdapter $client): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasRole('super-admin'), 403);

        $connection = GoogleSearchConsoleConnection::query()
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (! $connection?->isConnected()) {
            return response()->json([
                'message' => 'Google Search Console is not connected.',
            ], 409);
        }

        $view = (string) $request->query('view', 'query');
        $timeframe = $this->searchConsoleTimeframe((string) $request->query('timeframe', '28d'));

        try {
            return match ($view) {
                'page' => response()->json([
                    'data' => $this->performancePayload($client, $connection, ['page'], 'page', $timeframe),
                ]),
                'query_by_page' => response()->json([
                    'data' => $this->performancePayload($client, $connection, ['page', 'query'], 'query_by_page', $timeframe),
                ]),
                'comparison' => response()->json([
                    'data' => $this->comparisonPayload($client, $connection, $timeframe),
                ]),
                default => response()->json([
                    'data' => $this->performancePayload($client, $connection, ['query'], 'query', $timeframe),
                ]),
            };
        } catch (GoogleSearchConsoleException $exception) {
            $connection->forceFill([
                'last_error' => $exception->getMessage(),
            ])->save();

            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
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
            $user->hasRole('super-admin') ? [
                'label' => 'Marketing',
                'url' => route('admin.marketing.index', absolute: false),
                'active' => $request->routeIs('admin.marketing.*'),
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
     * Return available Search Console views for the marketing page.
     *
     * @return array<int, array<string, string>>
     */
    private function searchConsoleViews(): array
    {
        return [
            [
                'key' => 'query',
                'label' => 'Query Performance',
                'description' => 'Top search queries by clicks, impressions, CTR, and average position.',
            ],
            [
                'key' => 'page',
                'label' => 'Page Performance',
                'description' => 'Landing pages receiving organic search impressions and clicks.',
            ],
            [
                'key' => 'query_by_page',
                'label' => 'Query By Page',
                'description' => 'Which queries are driving impressions to each marketing URL.',
            ],
            [
                'key' => 'comparison',
                'label' => 'Period Comparison',
                'description' => 'Compare last 7 days against 28-day pace and recent 24-hour movement.',
            ],
            [
                'key' => 'report',
                'label' => 'Markdown Report',
                'description' => 'Download the current Search Console analysis and recommendations.',
            ],
        ];
    }

    /**
     * Build a normalized Search Console performance payload.
     *
     * @param array<int, string> $dimensions
     * @return array<string, mixed>
     */
    private function performancePayload(
        GoogleSearchConsoleAdapter $client,
        GoogleSearchConsoleConnection $connection,
        array $dimensions,
        string $view,
        array $timeframe
    ): array {
        $startDate = now()->subDays((int) $timeframe['days']);
        $endDate = $timeframe['key'] === '24h'
            ? now()
            : now()->subDay();
        $requestDimensions = $this->searchConsoleDimensions($dimensions, $timeframe);
        $performance = $client->searchAnalytics(
            $connection,
            $startDate,
            $endDate,
            $requestDimensions,
            250,
            dataState: $this->searchConsoleDataState($timeframe)
        );

        return [
            'view' => $view,
            'timeframe' => $timeframe,
            'dateRange' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'rows' => $this->aggregateSearchConsoleRows($performance['rows'] ?? [], $requestDimensions, $dimensions),
            'metadata' => $performance['metadata'] ?? [],
        ];
    }

    /**
     * Build a current-period comparison payload.
     *
     * @return array<string, mixed>
     */
    private function comparisonPayload(
        GoogleSearchConsoleAdapter $client,
        GoogleSearchConsoleConnection $connection,
        array $timeframe
    ): array {
        $days = (int) $timeframe['days'];
        $currentStart = now()->subDays($days);
        $currentEnd = $timeframe['key'] === '24h'
            ? now()
            : now()->subDay();
        $priorStart = now()->subDays($days * 2);
        $priorEnd = $timeframe['key'] === '24h'
            ? now()->subDay()
            : now()->subDays($days + 1);
        $dimensions = $this->searchConsoleDimensions(['query'], $timeframe);
        $current = $client->searchAnalytics(
            $connection,
            $currentStart,
            $currentEnd,
            $dimensions,
            250,
            dataState: $this->searchConsoleDataState($timeframe)
        );
        $prior = $client->searchAnalytics(
            $connection,
            $priorStart,
            $priorEnd,
            $dimensions,
            250,
            dataState: $this->searchConsoleDataState($timeframe)
        );
        $priorByQuery = collect($this->aggregateSearchConsoleRows($prior['rows'] ?? [], $dimensions, ['query']))
            ->keyBy(fn (array $row): string => (string) ($row['query'] ?? ''));

        return [
            'view' => 'comparison',
            'timeframe' => $timeframe,
            'dateRange' => [
                'current' => [
                    'start' => $currentStart->toDateString(),
                    'end' => $currentEnd->toDateString(),
                ],
                'prior' => [
                    'start' => $priorStart->toDateString(),
                    'end' => $priorEnd->toDateString(),
                ],
            ],
            'rows' => collect($this->aggregateSearchConsoleRows($current['rows'] ?? [], $dimensions, ['query']))
                ->map(function (array $row) use ($priorByQuery): array {
                    $query = (string) ($row['query'] ?? '');
                    $priorRow = $priorByQuery->get($query, []);

                    return [
                        'query' => $query,
                        'clicks' => (int) ($row['clicks'] ?? 0),
                        'priorClicks' => (int) ($priorRow['clicks'] ?? 0),
                        'clickDelta' => (int) ($row['clicks'] ?? 0) - (int) ($priorRow['clicks'] ?? 0),
                        'impressions' => (int) ($row['impressions'] ?? 0),
                        'priorImpressions' => (int) ($priorRow['impressions'] ?? 0),
                        'impressionDelta' => (int) ($row['impressions'] ?? 0) - (int) ($priorRow['impressions'] ?? 0),
                        'ctr' => (float) ($row['ctr'] ?? 0),
                        'position' => (float) ($row['position'] ?? 0),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * Normalize a Search Console row for UI rendering.
     *
     * @param array<string, mixed> $row
     * @param array<int, string> $dimensions
     * @return array<string, mixed>
     */
    private function searchConsoleRow(array $row, array $dimensions): array
    {
        $normalized = [
            'clicks' => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0),
            'position' => (float) ($row['position'] ?? 0),
        ];

        foreach ($dimensions as $index => $dimension) {
            $normalized[$dimension] = (string) data_get($row, 'keys.' . $index, '');
        }

        return $normalized;
    }

    /**
     * Aggregate Search Console rows back to visible dimensions.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $requestDimensions
     * @param array<int, string> $displayDimensions
     * @return array<int, array<string, mixed>>
     */
    private function aggregateSearchConsoleRows(array $rows, array $requestDimensions, array $displayDimensions): array
    {
        return collect($rows)
            ->map(fn (array $row): array => $this->searchConsoleRow($row, $requestDimensions))
            ->groupBy(function (array $row) use ($displayDimensions): string {
                return collect($displayDimensions)
                    ->map(fn (string $dimension): string => (string) ($row[$dimension] ?? ''))
                    ->implode("\n");
            })
            ->map(function ($group) use ($displayDimensions): array {
                $first = $group->first();
                $clicks = (int) $group->sum('clicks');
                $impressions = (int) $group->sum('impressions');
                $weightedPosition = (float) $group->sum(
                    fn (array $row): float => ((float) ($row['position'] ?? 0)) * ((int) ($row['impressions'] ?? 0))
                );
                $row = [
                    'clicks' => $clicks,
                    'impressions' => $impressions,
                    'ctr' => $impressions > 0 ? $clicks / $impressions : 0.0,
                    'position' => $impressions > 0 ? $weightedPosition / $impressions : 0.0,
                ];

                foreach ($displayDimensions as $dimension) {
                    $row[$dimension] = (string) ($first[$dimension] ?? '');
                }

                return $row;
            })
            ->sortByDesc('impressions')
            ->values()
            ->all();
    }

    /**
     * Return request dimensions, including hour for partial hourly data.
     *
     * @param array<int, string> $dimensions
     * @param array<string, int|string> $timeframe
     * @return array<int, string>
     */
    private function searchConsoleDimensions(array $dimensions, array $timeframe): array
    {
        if ($timeframe['key'] !== '24h') {
            return $dimensions;
        }

        return array_values(array_unique(array_merge(['hour'], $dimensions)));
    }

    /**
     * Return the Search Console data state for the selected timeframe.
     *
     * @param array<string, int|string> $timeframe
     */
    private function searchConsoleDataState(array $timeframe): ?string
    {
        return $timeframe['key'] === '24h'
            ? 'hourly_all'
            : null;
    }

    /**
     * Return available Search Console timeframes.
     *
     * @return array<int, array<string, int|string>>
     */
    private function searchConsoleTimeframes(): array
    {
        return [
            [
                'key' => '24h',
                'label' => '24 Hours',
                'days' => 1,
            ],
            [
                'key' => '7d',
                'label' => '7 Days',
                'days' => 7,
            ],
            [
                'key' => '28d',
                'label' => '28 Days',
                'days' => 28,
            ],
            [
                'key' => '90d',
                'label' => '90 Days',
                'days' => 90,
            ],
        ];
    }

    /**
     * Return a safe Search Console timeframe from request input.
     *
     * @return array<string, int|string>
     */
    private function searchConsoleTimeframe(string $key): array
    {
        foreach ($this->searchConsoleTimeframes() as $timeframe) {
            if ($timeframe['key'] === $key) {
                return $timeframe;
            }
        }

        return [
            'key' => '28d',
            'label' => '28 Days',
            'days' => 28,
        ];
    }
}
