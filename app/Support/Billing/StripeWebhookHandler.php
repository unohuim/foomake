<?php

namespace App\Support\Billing;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Verify and apply Stripe webhook events that affect tenant billing access.
 */
class StripeWebhookHandler
{
    /**
     * Create a new webhook handler instance.
     */
    public function __construct(private readonly StripeBillingService $billingService)
    {
    }

    /**
     * Verify and apply the webhook request.
     */
    public function handle(Request $request): void
    {
        $payload = $request->getContent();
        $this->verifySignature($payload, (string) $request->header('Stripe-Signature'));

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            throw new HttpException(400, 'Invalid Stripe payload.');
        }

        $type = (string) ($event['type'] ?? '');
        $object = $event['data']['object'] ?? [];

        if (! is_array($object)) {
            throw new HttpException(400, 'Invalid Stripe object.');
        }

        match ($type) {
            'checkout.session.completed' => $this->billingService->applyCheckoutSession($object),
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->billingService->applySubscription($object),
            default => null,
        };
    }

    /**
     * Verify the Stripe request signature.
     */
    private function verifySignature(string $payload, string $signatureHeader): void
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            throw new HttpException(500, 'Stripe webhook secret is not configured.');
        }

        $parts = collect(explode(',', $signatureHeader))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, '');

                return [$key => $value];
            });

        $timestamp = (string) $parts->get('t', '');
        $signature = (string) $parts->get('v1', '');

        if ($timestamp === '' || $signature === '') {
            throw new HttpException(400, 'Invalid Stripe signature.');
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        if (! hash_equals($expected, $signature)) {
            throw new HttpException(400, 'Invalid Stripe signature.');
        }
    }

}
