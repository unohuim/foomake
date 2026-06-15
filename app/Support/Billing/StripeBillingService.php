<?php

namespace App\Support\Billing;

use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Synchronize Stripe billing state into tenant access records.
 */
class StripeBillingService
{
    /**
     * Cached subscription plan label for the current request.
     */
    private ?string $subscriptionPlanLabel = null;

    /**
     * Synchronize a completed Checkout Session directly from Stripe.
     */
    public function syncCheckoutSession(string $sessionId): ?Tenant
    {
        $sessionId = trim($sessionId);

        if ($sessionId === '') {
            return null;
        }

        $session = $this->stripeObject(
            sprintf('https://api.stripe.com/v1/checkout/sessions/%s?expand[]=subscription', rawurlencode($sessionId))
        );

        if (! is_array($session)) {
            return null;
        }

        $tenant = $this->applyCheckoutSession($session);

        if (! $tenant) {
            return null;
        }

        $subscription = $session['subscription'] ?? null;

        if (is_string($subscription) && $subscription !== '') {
            $subscription = $this->stripeObject(
                sprintf('https://api.stripe.com/v1/subscriptions/%s', rawurlencode($subscription))
            );
        }

        if (is_array($subscription)) {
            $tenant = $this->applySubscription($subscription) ?? $tenant;
        }

        return $tenant->fresh();
    }

    /**
     * Synchronize a Stripe subscription directly from Stripe.
     */
    public function syncSubscription(string $subscriptionId): ?Tenant
    {
        $subscriptionId = trim($subscriptionId);

        if ($subscriptionId === '') {
            return null;
        }

        $subscription = $this->stripeObject(
            sprintf('https://api.stripe.com/v1/subscriptions/%s', rawurlencode($subscriptionId))
        );

        if (! is_array($subscription)) {
            return null;
        }

        return $this->applySubscription($subscription);
    }

    /**
     * Apply a completed Checkout Session payload to the local tenant.
     *
     * @param array<string, mixed> $session
     */
    public function applyCheckoutSession(array $session): ?Tenant
    {
        $tenant = $this->resolveTenant($session);

        if (! $tenant) {
            return null;
        }

        $tenant->forceFill([
            'billing_provider' => 'stripe',
            'billing_provider_customer_id' => $session['customer'] ?? $tenant->billing_provider_customer_id,
            'billing_provider_subscription_id' => is_array($session['subscription'] ?? null)
                ? ($session['subscription']['id'] ?? $tenant->billing_provider_subscription_id)
                : ($session['subscription'] ?? $tenant->billing_provider_subscription_id),
        ])->save();

        return $tenant->fresh();
    }

    /**
     * Apply a Stripe subscription payload to the local tenant.
     *
     * @param array<string, mixed> $subscription
     */
    public function applySubscription(array $subscription): ?Tenant
    {
        $tenant = $this->resolveTenant($subscription);

        if (! $tenant) {
            return null;
        }

        $tenant->forceFill([
            'billing_provider' => 'stripe',
            'billing_provider_customer_id' => $subscription['customer'] ?? $tenant->billing_provider_customer_id,
            'billing_provider_subscription_id' => $subscription['id'] ?? $tenant->billing_provider_subscription_id,
            'billing_subscription_status' => $this->normalizedStatus((string) ($subscription['status'] ?? '')),
            'billing_subscription_ends_at' => $this->subscriptionEndsAt($subscription),
        ])->save();

        return $tenant->fresh();
    }

    /**
     * Resolve the current configured subscription plan label.
     */
    public function subscriptionPlanLabel(): ?string
    {
        if ($this->subscriptionPlanLabel !== null) {
            return $this->subscriptionPlanLabel;
        }

        $secret = (string) config('services.stripe.secret');
        $priceId = (string) config('services.stripe.subscription_price_id');

        if ($secret === '' || $priceId === '') {
            return null;
        }

        $price = $this->stripeObject(
            sprintf('https://api.stripe.com/v1/prices/%s?expand[]=product', rawurlencode($priceId))
        );

        if (! is_array($price)) {
            return null;
        }

        $product = is_array($price['product'] ?? null) ? $price['product'] : null;

        $label = trim((string) ($product['name'] ?? $price['nickname'] ?? $price['id'] ?? ''));

        $this->subscriptionPlanLabel = $label !== '' ? $label : null;

        return $this->subscriptionPlanLabel;
    }

    /**
     * Fetch and decode a Stripe object.
     *
     * @return array<string, mixed>|null
     */
    private function stripeObject(string $url): ?array
    {
        $secret = (string) config('services.stripe.secret');

        if ($secret === '') {
            throw new RuntimeException('Stripe billing is not configured.');
        }

        $response = Http::acceptJson()
            ->withToken($secret)
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException('Stripe billing state could not be synchronized.');
        }

        $object = $response->json();

        return is_array($object) ? $object : null;
    }

    /**
     * Resolve the tenant referenced by a Stripe object.
     *
     * @param array<string, mixed> $object
     */
    private function resolveTenant(array $object): ?Tenant
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
            Log::warning('Stripe billing object could not resolve tenant.', [
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
