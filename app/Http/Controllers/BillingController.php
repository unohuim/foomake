<?php

namespace App\Http\Controllers;

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
    public function index(Request $request, TenantBillingEntitlement $entitlement): View
    {
        $tenant = $request->user()->tenant;

        return view('billing.index', [
            'tenant' => $tenant,
            'hasAccess' => $entitlement->hasAccess($tenant),
            'trialActive' => $entitlement->isTrialActive($tenant),
            'billingExempt' => $entitlement->isBillingExempt($tenant),
            'subscriptionActive' => $entitlement->hasActiveSubscription($tenant),
            'canManageBilling' => $request->user()->can('billing-subscription-manage'),
        ]);
    }
}
