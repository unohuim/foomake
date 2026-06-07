<?php

namespace App\Http\Controllers;

use App\Support\Billing\StripeWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receive billing-provider webhook events.
 */
class BillingWebhookController extends Controller
{
    /**
     * Apply Stripe webhook events that affect tenant billing access.
     */
    public function store(Request $request, StripeWebhookHandler $handler): JsonResponse
    {
        $handler->handle($request);

        return response()->json(['received' => true]);
    }
}
