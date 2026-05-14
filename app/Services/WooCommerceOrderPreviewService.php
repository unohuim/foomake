<?php

namespace App\Services;

use App\Integrations\WooCommerce\WooCommerceClient;
use App\Integrations\WooCommerce\WooCommerceException;
use App\Models\ExternalProductSourceConnection;

/**
 * Normalize WooCommerce orders into the shared import-preview contract.
 */
class WooCommerceOrderPreviewService
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
        $orders = $this->client->listOrders(
            (string) $connection->store_url,
            (string) $connection->consumer_key,
            (string) $connection->consumer_secret
        );

        return array_map(fn (array $order): array => $this->normalizeOrder($order), $orders);
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     *
     * @throws WooCommerceException
     */
    private function normalizeOrder(array $order): array
    {
        if (! isset($order['id'])) {
            throw new WooCommerceException('The WooCommerce order response was malformed.');
        }

        $status = trim((string) ($order['status'] ?? ''));

        if ($status === '') {
            throw new WooCommerceException('The WooCommerce order response was missing a status.');
        }

        if (! is_array($order['line_items'] ?? null) || $order['line_items'] === []) {
            throw new WooCommerceException('The WooCommerce order response was missing line items.');
        }

        $billing = is_array($order['billing'] ?? null) ? $order['billing'] : [];
        $customerName = trim(
            trim((string) ($billing['first_name'] ?? '')) . ' ' . trim((string) ($billing['last_name'] ?? ''))
        );

        if ($customerName === '') {
            $customerName = (string) ($billing['company'] ?? '');
        }

        if ($customerName === '') {
            $customerName = 'Woo Customer ' . (string) ($order['customer_id'] ?? $order['id']);
        }

        $date = substr((string) ($order['date_created'] ?? ''), 0, 10);

        return [
            'external_id' => (string) $order['id'],
            'external_source' => ExternalProductSourceConnection::SOURCE_WOOCOMMERCE,
            'external_status' => $status,
            'date' => $date !== '' ? $date : null,
            'customer' => [
                'external_id' => isset($order['customer_id']) ? (string) $order['customer_id'] : '',
                'name' => $customerName,
                'email' => (string) ($billing['email'] ?? ''),
                'phone' => (string) ($billing['phone'] ?? ''),
                'address_line_1' => (string) ($billing['address_1'] ?? ''),
                'address_line_2' => (string) ($billing['address_2'] ?? ''),
                'city' => (string) ($billing['city'] ?? ''),
                'region' => (string) ($billing['state'] ?? ''),
                'postal_code' => (string) ($billing['postcode'] ?? ''),
                'country_code' => (string) ($billing['country'] ?? ''),
            ],
            'lines' => array_map(function (array $line): array {
                if (! isset($line['id'], $line['name'], $line['quantity'])) {
                    throw new WooCommerceException('The WooCommerce order line response was malformed.');
                }

                $quantity = (string) $line['quantity'];
                $unitPriceCents = $this->decimalToCents($line['price'] ?? 0);
                $productExternalId = trim((string) ($line['sku'] ?? ''));

                if ($productExternalId === '') {
                    $variationId = trim((string) ($line['variation_id'] ?? ''));
                    $productId = trim((string) ($line['product_id'] ?? ''));
                    $productExternalId = $variationId !== '' ? $variationId : $productId;
                }

                return [
                    'external_id' => (string) $line['id'],
                    'product_external_id' => $productExternalId,
                    'name' => (string) $line['name'],
                    'quantity' => bcadd($quantity, '0', 6),
                    'unit_price_cents' => $unitPriceCents,
                    'currency_code' => 'USD',
                ];
            }, $order['line_items']),
        ];
    }

    /**
     * Convert a decimal-like value to integer cents without float math.
     */
    private function decimalToCents(mixed $value): int
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return 0;
        }

        $negative = str_starts_with($normalized, '-');

        if ($negative) {
            $normalized = substr($normalized, 1);
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
        $whole = preg_replace('/\D/', '', $whole) ?? '0';
        $fraction = preg_replace('/\D/', '', $fraction) ?? '0';
        $fraction = str_pad(substr($fraction, 0, 3), 3, '0');
        $cents = ((int) $whole * 100) + (int) substr($fraction, 0, 2);

        if ((int) $fraction[2] >= 5) {
            $cents++;
        }

        return $negative ? $cents * -1 : $cents;
    }
}
