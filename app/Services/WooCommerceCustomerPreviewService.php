<?php

namespace App\Services;

use App\Integrations\WooCommerce\WooCommerceClient;
use App\Integrations\WooCommerce\WooCommerceException;
use App\Models\ExternalProductSourceConnection;

/**
 * Normalize WooCommerce customers into the shared import-preview contract.
 */
class WooCommerceCustomerPreviewService
{
    public function __construct(
        private readonly WooCommerceClient $client
    ) {
    }

    /**
     * Fetch and normalize import preview rows.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws WooCommerceException
     */
    public function previewRows(ExternalProductSourceConnection $connection): array
    {
        $customers = $this->client->listCustomers(
            (string) $connection->store_url,
            (string) $connection->consumer_key,
            (string) $connection->consumer_secret
        );

        return array_map(fn (array $customer): array => $this->normalizeCustomer($customer), $customers);
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return array<string, mixed>
     *
     * @throws WooCommerceException
     */
    private function normalizeCustomer(array $customer): array
    {
        if (! isset($customer['id'])) {
            throw new WooCommerceException('The WooCommerce customer response was malformed.');
        }

        $billing = is_array($customer['billing'] ?? null) ? $customer['billing'] : [];
        $firstName = trim((string) ($customer['first_name'] ?? ''));
        $lastName = trim((string) ($customer['last_name'] ?? ''));
        $name = trim($firstName . ' ' . $lastName);

        if ($name === '') {
            $name = (string) ($billing['company'] ?? '');
        }

        if ($name === '') {
            $name = (string) ($customer['username'] ?? '');
        }

        if ($name === '') {
            $name = (string) ($customer['email'] ?? '');
        }

        if ($name === '') {
            $name = 'Woo Customer ' . (string) $customer['id'];
        }

        return [
            'external_id' => (string) $customer['id'],
            'name' => $name,
            'email' => (string) ($customer['email'] ?? ''),
            'phone' => (string) ($billing['phone'] ?? ''),
            'address_line_1' => (string) ($billing['address_1'] ?? ''),
            'address_line_2' => (string) ($billing['address_2'] ?? ''),
            'city' => (string) ($billing['city'] ?? ''),
            'region' => (string) ($billing['state'] ?? ''),
            'postal_code' => (string) ($billing['postcode'] ?? ''),
            'country_code' => (string) ($billing['country'] ?? ''),
        ];
    }
}
