<?php

use App\Models\Tenant;
use App\Support\Billing\StripeBillingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
