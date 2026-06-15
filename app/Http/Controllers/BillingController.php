<?php

namespace App\Http\Controllers;

use App\Support\Billing\StripeBillingService;
use App\Support\Billing\TenantBillingEntitlement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Show the tenant platform billing status and management entry point.
 */
class BillingController extends Controller
{
    /**
     * Display tenant billing status.
     */
    public function index(
        Request $request,
        TenantBillingEntitlement $entitlement,
        StripeBillingService $billing
    ): View {
        $tenant = $request->user()->tenant;
        $checkoutStatus = (string) $request->query('checkout', '');
        $checkoutSessionId = (string) $request->query('session_id', '');
        $checkoutMessage = null;
        $subscriptionPlanName = null;

        try {
            $subscriptionPlanName = $billing->subscriptionPlanLabel();
        } catch (\Throwable) {
            // Keep billing visible even if Stripe metadata is temporarily unavailable.
        }

        if ($checkoutStatus === 'success' && $checkoutSessionId !== '') {
            try {
                $syncedTenant = $billing->syncCheckoutSession($checkoutSessionId);

                if ($syncedTenant) {
                    $tenant = $syncedTenant;
                }
            } catch (\Throwable) {
                // Keep the billing page reachable even if Stripe is briefly unavailable.
            }

            if ($entitlement->hasAccess($tenant)) {
                $checkoutMessage = $subscriptionPlanName !== null
                    ? __('Subscription confirmed for :plan.', ['plan' => $subscriptionPlanName])
                    : __('Subscription confirmed.');
            } else {
                $checkoutMessage = __('Payment received. Waiting for Stripe confirmation.');
            }
        } elseif ($checkoutStatus === 'cancelled') {
            $checkoutMessage = __('Checkout cancelled. No billing changes were made.');
        }

        return view('billing.index', [
            'tenant' => $tenant,
            'hasAccess' => $entitlement->hasAccess($tenant),
            'trialActive' => $entitlement->isTrialActive($tenant),
            'billingExempt' => $entitlement->isBillingExempt($tenant),
            'subscriptionActive' => $entitlement->hasActiveSubscription($tenant),
            'subscriptionPlanName' => $subscriptionPlanName,
            'checkoutMessage' => $checkoutMessage,
            'canManageBilling' => $request->user()->can('billing-subscription-manage'),
        ]);
    }
}
