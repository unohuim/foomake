<?php

namespace App\Support\Billing;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Verify and apply Stripe webhook events that affect tenant billing access.
 */
class StripeWebhookHandler
{
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
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($object),
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->handleSubscriptionChanged($object),
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

    /**
     * Persist Stripe customer and subscription identifiers from completed Checkout.
     *
     * @param array<string, mixed> $session
     */
    private function handleCheckoutSessionCompleted(array $session): void
    {
        $tenant = $this->tenantFromStripeObject($session);

        if (! $tenant) {
            return;
        }

        $tenant->forceFill([
            'billing_provider' => 'stripe',
            'billing_provider_customer_id' => $session['customer'] ?? $tenant->billing_provider_customer_id,
            'billing_provider_subscription_id' => $session['subscription'] ?? $tenant->billing_provider_subscription_id,
        ])->save();
    }

    /**
     * Persist provider-confirmed subscription status.
     *
     * @param array<string, mixed> $subscription
     */
    private function handleSubscriptionChanged(array $subscription): void
    {
        $tenant = $this->tenantFromStripeObject($subscription);

        if (! $tenant) {
            return;
        }

        $tenant->forceFill([
            'billing_provider' => 'stripe',
            'billing_provider_customer_id' => $subscription['customer'] ?? $tenant->billing_provider_customer_id,
            'billing_provider_subscription_id' => $subscription['id'] ?? $tenant->billing_provider_subscription_id,
            'billing_subscription_status' => $this->normalizedStatus((string) ($subscription['status'] ?? '')),
            'billing_subscription_ends_at' => $this->subscriptionEndsAt($subscription),
        ])->save();
    }

    /**
     * Resolve the tenant referenced by a Stripe object.
     *
     * @param array<string, mixed> $object
     */
    private function tenantFromStripeObject(array $object): ?Tenant
    {
        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
        $tenantId = (int) ($metadata['tenant_id'] ?? $object['client_reference_id'] ?? 0);

        if ($tenantId > 0) {
            return Tenant::query()->find($tenantId);
        }

        $subscriptionId = (string) ($object['id'] ?? $object['subscription'] ?? '');
        $customerId = (string) ($object['customer'] ?? '');

        $tenant = null;

        if ($subscriptionId !== '') {
            $tenant = Tenant::query()
                ->where('billing_provider', 'stripe')
                ->where('billing_provider_subscription_id', $subscriptionId)
                ->first();
        }

        if (! $tenant && $customerId !== '') {
            $tenant = Tenant::query()
                ->where('billing_provider', 'stripe')
                ->where('billing_provider_customer_id', $customerId)
                ->first();
        }

        if (! $tenant) {
            Log::warning('Stripe webhook could not resolve tenant.', [
                'stripe_object_id' => $object['id'] ?? null,
                'stripe_customer_id' => $object['customer'] ?? null,
            ]);
        }

        return $tenant;
    }

    /**
     * Normalize Stripe subscription statuses into canonical tenant billing statuses.
     */
    private function normalizedStatus(string $status): ?string
    {
        return match ($status) {
            Tenant::BILLING_SUBSCRIPTION_STATUS_ACTIVE,
            Tenant::BILLING_SUBSCRIPTION_STATUS_TRIALING,
            Tenant::BILLING_SUBSCRIPTION_STATUS_INCOMPLETE,
            Tenant::BILLING_SUBSCRIPTION_STATUS_INCOMPLETE_EXPIRED,
            Tenant::BILLING_SUBSCRIPTION_STATUS_PAST_DUE,
            Tenant::BILLING_SUBSCRIPTION_STATUS_CANCELED,
            Tenant::BILLING_SUBSCRIPTION_STATUS_UNPAID,
            Tenant::BILLING_SUBSCRIPTION_STATUS_PAUSED => $status,
            default => null,
        };
    }

    /**
     * Resolve the local subscription access end timestamp.
     *
     * @param array<string, mixed> $subscription
     */
    private function subscriptionEndsAt(array $subscription): ?Carbon
    {
        $endedAt = (int) ($subscription['ended_at'] ?? 0);

        if ($endedAt > 0) {
            return Carbon::createFromTimestamp($endedAt);
        }

        return null;
    }
}
