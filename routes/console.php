<?php

use App\Models\Tenant;
use App\Models\GoogleSearchConsoleConnection;
use App\Support\Billing\StripeBillingService;
use App\Services\GoogleSearchConsoleReportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:sync-stripe {tenantId?}', function (StripeBillingService $billing, ?string $tenantId = null): int {
    $query = Tenant::query()
        ->where('billing_provider', 'stripe')
        ->whereNotNull('billing_provider_subscription_id');

    if ($tenantId !== null) {
        $query->whereKey($tenantId);
    }

    $synced = 0;

    foreach ($query->cursor() as $tenant) {
        try {
            if ($billing->syncSubscription((string) $tenant->billing_provider_subscription_id)) {
                $synced++;
            }
        } catch (\Throwable $exception) {
            $this->warn(sprintf(
                'Tenant %d could not be synchronized: %s',
                $tenant->id,
                $exception->getMessage()
            ));
        }
    }

    $this->info(sprintf('Synced %d tenant(s).', $synced));

    return 0;
})->purpose('Synchronize tenant billing state with Stripe.');

Artisan::command('search-console:report {--path=search_console_report.md}', function (
    GoogleSearchConsoleReportService $reports
): int {
    $query = GoogleSearchConsoleConnection::query()
        ->where('status', GoogleSearchConsoleConnection::STATUS_CONNECTED)
        ->whereNotNull('refresh_token');

    $siteUrl = config('services.google_search_console.site_url');

    if (is_string($siteUrl) && $siteUrl !== '') {
        $query->where('site_url', $siteUrl);
    }

    $connections = $query->get();

    if ($connections->isEmpty()) {
        $this->error('No connected FooMake Google Search Console connection found.');

        return 1;
    }

    if ($connections->count() > 1) {
        $this->error('Multiple Google Search Console connections found. Configure GOOGLE_SEARCH_CONSOLE_SITE_URL.');

        return 1;
    }

    $path = (string) $this->option('path');
    $targetPath = str_starts_with($path, DIRECTORY_SEPARATOR)
        ? $path
        : base_path($path);
    $targetDirectory = dirname($targetPath);

    if (! File::isDirectory($targetDirectory)) {
        $this->error('Report directory does not exist: ' . $targetDirectory);

        return 1;
    }

    /** @var GoogleSearchConsoleConnection $connection */
    $connection = $connections->first();

    File::put($targetPath, $reports->markdown($connection, now()));

    $this->info('Search Console report written to ' . $targetPath);

    return 0;
})->purpose('Write a Google Search Console markdown report with analysis and recommendations.');
