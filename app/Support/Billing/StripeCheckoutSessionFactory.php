<?php

namespace App\Support\Billing;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Create Stripe Checkout sessions for tenant platform subscriptions.
 */
class StripeCheckoutSessionFactory
{
    /**
     * Create a hosted Stripe Checkout session and return its redirect URL.
     */
    public function createForTenant(Tenant $tenant): string
    {
        $secret = (string) config('services.stripe.secret');
        $priceId = (string) config('services.stripe.subscription_price_id');

        if ($secret === '' || $priceId === '') {
            throw new RuntimeException('Stripe billing is not configured.');
        }

        $payload = [
            'mode' => 'subscription',
            'success_url' => route('admin.index', ['tab' => 'billing']) . '&checkout=success&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('admin.index', ['tab' => 'billing', 'checkout' => 'cancelled']),
            'client_reference_id' => (string) $tenant->id,
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
            ],
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'subscription_data' => [
                'metadata' => [
                    'tenant_id' => (string) $tenant->id,
                ],
            ],
        ];

        if ($tenant->billing_provider_customer_id) {
            $payload['customer'] = $tenant->billing_provider_customer_id;
        } else {
            $ownerEmail = $tenant->users()->orderBy('id')->value('email');

            if ($ownerEmail) {
                $payload['customer_email'] = $ownerEmail;
            }
        }

        if ($tenant->trial_ends_at !== null && $tenant->trial_ends_at->isFuture()) {
            $payload['subscription_data']['trial_end'] = $tenant->trial_ends_at->timestamp;
        }

        $response = Http::asForm()
            ->withToken($secret)
            ->post('https://api.stripe.com/v1/checkout/sessions', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Stripe checkout session could not be created.');
        }

        $url = (string) $response->json('url');

        if ($url === '') {
            throw new RuntimeException('Stripe checkout session did not return a redirect URL.');
        }

        return $url;
    }
}
