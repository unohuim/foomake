<?php

namespace App\Http\Controllers;

use App\Support\Billing\StripeCheckoutSessionFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Start provider-hosted tenant subscription checkout.
 */
class BillingCheckoutController extends Controller
{
    /**
     * Redirect a billing admin to Stripe Checkout.
     */
    public function store(Request $request, StripeCheckoutSessionFactory $checkout): RedirectResponse
    {
        Gate::authorize('billing-subscription-manage');

        if (! $request->user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $url = $checkout->createForTenant($request->user()->tenant);

        return redirect()->away($url);
    }
}
