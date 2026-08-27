<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Integrations\GoogleSearchConsole\GoogleSearchConsoleAdapter;
use App\Integrations\GoogleSearchConsole\GoogleSearchConsoleException;
use App\Models\GoogleSearchConsoleConnection;
use App\Models\User;
use App\Support\Inertia\AdminHubPayloadBuilder;
use App\Support\Inertia\AuthShellPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
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
    public function __invoke(
        Request $request,
        AuthShellPayloadBuilder $authShellPayloadBuilder,
        AdminHubPayloadBuilder $adminHubPayloadBuilder
    ): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasRole('super-admin'), 403);

        $connection = GoogleSearchConsoleConnection::query()
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (! $connection?->isConnected()) {
            return redirect()->route('admin.index', [
                'tab' => 'connectors',
                'connector' => 'google',
                'notice' => 'marketing_google_required',
            ]);
        }

        return Inertia::render('Admin/Marketing', [
            'shell' => $authShellPayloadBuilder->build($request),
            'searchConsole' => $adminHubPayloadBuilder->marketingPayload($user),
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
