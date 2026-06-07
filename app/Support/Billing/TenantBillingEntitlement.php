<?php

namespace App\Support\Billing;

use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Resolve whether a tenant currently has platform application access.
 */
class TenantBillingEntitlement
{
    /**
     * Determine whether the tenant may access protected application routes.
     */
    public function hasAccess(Tenant $tenant, ?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->isTrialActive($tenant, $now)
            || $this->isBillingExempt($tenant, $now)
            || $this->hasActiveSubscription($tenant, $now);
    }

    /**
     * Determine whether the tenant is inside the app-owned trial window.
     */
    public function isTrialActive(Tenant $tenant, ?Carbon $now = null): bool
    {
        $now ??= now();

        return $tenant->trial_ends_at !== null && $tenant->trial_ends_at->greaterThanOrEqualTo($now);
    }

    /**
     * Determine whether the tenant has a temporary platform-owner exemption.
     */
    public function isBillingExempt(Tenant $tenant, ?Carbon $now = null): bool
    {
        $now ??= now();

        return $tenant->billing_exempt_until !== null
            && $tenant->billing_exempt_until->greaterThanOrEqualTo($now);
    }

    /**
     * Determine whether the tenant has a provider-confirmed active subscription.
     */
    public function hasActiveSubscription(Tenant $tenant, ?Carbon $now = null): bool
    {
        $now ??= now();

        if (! in_array($tenant->billing_subscription_status, [
            Tenant::BILLING_SUBSCRIPTION_STATUS_ACTIVE,
            Tenant::BILLING_SUBSCRIPTION_STATUS_TRIALING,
        ], true)) {
            return false;
        }

        return $tenant->billing_subscription_ends_at === null
            || $tenant->billing_subscription_ends_at->greaterThanOrEqualTo($now);
    }
}
