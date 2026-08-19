<?php

namespace App\Services;

use App\Integrations\GoogleSearchConsole\GoogleSearchConsoleAdapter;
use App\Models\GoogleSearchConsoleConnection;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Build a deterministic Search Console performance report.
 */
class GoogleSearchConsoleReportService
{
    /**
     * @param GoogleSearchConsoleAdapter $adapter
     */
    public function __construct(private readonly GoogleSearchConsoleAdapter $adapter)
    {
    }

    /**
     * Build the Search Console report markdown.
     */
    public function markdown(GoogleSearchConsoleConnection $connection, CarbonInterface $asOf): string
    {
        $sections = [
            'Last 28 Days' => $this->fetchWindow($connection, $asOf->copy()->subDays(28), $asOf->copy()->subDay(), 25),
            'Last 7 Days' => $this->fetchWindow($connection, $asOf->copy()->subDays(7), $asOf->copy()->subDay(), 25),
            'Last 24 Hours' => $this->fetchWindow($connection, $asOf->copy()->subDay(), $asOf, 25),
        ];

        $recommendations = $this->recommendations($sections);

        return implode("\n", [
            '# Google Search Console Report',
            '',
            '- Generated at: ' . $asOf->toDateTimeString(),
            '- Site: ' . ($connection->site_url ?: 'Not selected'),
            '- Note: Search Console data is date-based. The 24-hour section uses the most recent calendar dates available from Google.',
            '',
            '## Summary',
            '',
            $this->summaryTable($sections),
            '',
            '## Analysis',
            '',
            $this->analysis($sections),
            '',
            '## Next-Step Recommendations',
            '',
            $this->recommendationList($recommendations),
            '',
            '## Query Detail',
            '',
            $this->windowTables($sections),
            '',
        ]);
    }

    /**
     * Fetch and normalize one Search Console window.
     *
     * @return array{start: string, end: string, rows: list<array<string, mixed>>}
     */
    private function fetchWindow(
        GoogleSearchConsoleConnection $connection,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        int $rowLimit
    ): array {
        $response = $this->adapter->searchAnalytics($connection, $startDate, $endDate, ['query'], $rowLimit);

        return [
            'start' => $startDate->toDateString(),
            'end' => $endDate->toDateString(),
            'rows' => collect($response['rows'] ?? [])
                ->map(fn (array $row): array => [
                    'query' => (string) data_get($row, 'keys.0', ''),
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'ctr' => (float) ($row['ctr'] ?? 0),
                    'position' => (float) ($row['position'] ?? 0),
                ])
                ->filter(fn (array $row): bool => $row['query'] !== '')
                ->values()
                ->all(),
        ];
    }

    /**
     * Build the summary table.
     *
     * @param array<string, array{start: string, end: string, rows: list<array<string, mixed>>}> $sections
     */
    private function summaryTable(array $sections): string
    {
        $lines = [
            '| Window | Dates | Clicks | Impressions | Avg CTR | Avg Position |',
            '| --- | --- | ---: | ---: | ---: | ---: |',
        ];

        foreach ($sections as $label => $section) {
            $rows = collect($section['rows']);
            $clicks = (int) $rows->sum('clicks');
            $impressions = (int) $rows->sum('impressions');
            $ctr = $impressions > 0 ? $clicks / $impressions : 0;
            $position = $this->weightedPosition($rows);

            $lines[] = sprintf(
                '| %s | %s to %s | %d | %d | %s | %s |',
                $label,
                $section['start'],
                $section['end'],
                $clicks,
                $impressions,
                $this->percent($ctr),
                $this->number($position)
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Build a short deterministic analysis.
     *
     * @param array<string, array{start: string, end: string, rows: list<array<string, mixed>>}> $sections
     */
    private function analysis(array $sections): string
    {
        $last28 = collect($sections['Last 28 Days']['rows']);
        $last7 = collect($sections['Last 7 Days']['rows']);
        $topQuery = $last7->sortByDesc('impressions')->first();
        $zeroClickCount = $last7
            ->filter(fn (array $row): bool => $row['impressions'] >= 10 && $row['clicks'] === 0)
            ->count();
        $sevenDayImpressions = (int) $last7->sum('impressions');
        $expectedSevenDayImpressions = (int) round(((int) $last28->sum('impressions')) / 4);
        $pace = $expectedSevenDayImpressions > 0
            ? $sevenDayImpressions / $expectedSevenDayImpressions
            : 0;

        $lines = [];

        if ($topQuery) {
            $lines[] = '- Top current demand signal: `' . $topQuery['query'] . '` with '
                . $topQuery['impressions'] . ' impressions in the last 7 days.';
        }

        $lines[] = '- Last 7 days are running at ' . $this->number($pace) . 'x the 28-day impression pace.';
        $lines[] = '- ' . $zeroClickCount . ' queries have at least 10 impressions and zero clicks in the last 7 days.';

        return implode("\n", $lines);
    }

    /**
     * Build prioritized next-step recommendations.
     *
     * @param array<string, array{start: string, end: string, rows: list<array<string, mixed>>}> $sections
     * @return list<string>
     */
    private function recommendations(array $sections): array
    {
        $last7 = collect($sections['Last 7 Days']['rows']);
        $zeroClickOpportunities = $last7
            ->filter(fn (array $row): bool => $row['impressions'] >= 10 && $row['clicks'] === 0)
            ->sortByDesc('impressions')
            ->take(5)
            ->pluck('query')
            ->values();
        $clickedQueries = $last7
            ->filter(fn (array $row): bool => $row['clicks'] > 0)
            ->sortByDesc('clicks')
            ->take(3)
            ->pluck('query')
            ->values();

        $recommendations = [];

        if ($clickedQueries->isNotEmpty()) {
            $recommendations[] = 'Strengthen pages already earning clicks: ' . $clickedQueries->map(fn (string $query): string => '`' . $query . '`')->implode(', ') . '.';
        }

        if ($zeroClickOpportunities->isNotEmpty()) {
            $recommendations[] = 'Create or revise pages for high-impression zero-click queries: ' . $zeroClickOpportunities->map(fn (string $query): string => '`' . $query . '`')->implode(', ') . '.';
        }

        $recommendations[] = 'Use the top query themes as product/solution-page inputs, not only blog keywords.';
        $recommendations[] = 'Review title tags and meta descriptions on ranking pages where impressions rise but clicks stay flat.';
        $recommendations[] = 'Re-run this report weekly and compare the last 7 days against the prior 28-day pace.';

        return $recommendations;
    }

    /**
     * Format recommendations as markdown.
     *
     * @param list<string> $recommendations
     */
    private function recommendationList(array $recommendations): string
    {
        return collect($recommendations)
            ->map(fn (string $recommendation): string => '- ' . $recommendation)
            ->implode("\n");
    }

    /**
     * Build query-detail tables for each window.
     *
     * @param array<string, array{start: string, end: string, rows: list<array<string, mixed>>}> $sections
     */
    private function windowTables(array $sections): string
    {
        return collect($sections)
            ->map(function (array $section, string $label): string {
                $lines = [
                    '### ' . $label,
                    '',
                    '| Query | Clicks | Impressions | CTR | Position |',
                    '| --- | ---: | ---: | ---: | ---: |',
                ];

                foreach ($section['rows'] as $row) {
                    $lines[] = sprintf(
                        '| %s | %d | %d | %s | %s |',
                        str_replace('|', '\\|', $row['query']),
                        $row['clicks'],
                        $row['impressions'],
                        $this->percent($row['ctr']),
                        $this->number($row['position'])
                    );
                }

                if ($section['rows'] === []) {
                    $lines[] = '| No rows returned | 0 | 0 | 0.00% | 0.00 |';
                }

                return implode("\n", $lines);
            })
            ->implode("\n\n");
    }

    /**
     * Calculate an impressions-weighted average position.
     *
     * @param Collection<int, array<string, mixed>> $rows
     */
    private function weightedPosition(Collection $rows): float
    {
        $impressions = (int) $rows->sum('impressions');

        if ($impressions === 0) {
            return 0.0;
        }

        return (float) ($rows->sum(fn (array $row): float => $row['position'] * $row['impressions']) / $impressions);
    }

    /**
     * Format a decimal as a percentage.
     */
    private function percent(float $value): string
    {
        return number_format($value * 100, 2) . '%';
    }

    /**
     * Format a decimal.
     */
    private function number(float $value): string
    {
        return number_format($value, 2);
    }
}
