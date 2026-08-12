<?php

namespace App\Services;

use App\Integrations\WooCommerce\WooCommerceException;
use App\Models\WordPressPluginConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Fetch normalized WooCommerce order preview rows through the paired WordPress plugin.
 */
class WordPressPluginOrderPreviewService
{
    /**
     * Fetch normalized import preview rows from the paired WordPress plugin.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws WooCommerceException
     */
    public function previewRows(WordPressPluginConnection $connection): array
    {
        $siteUrl = rtrim((string) $connection->site_url, '/');
        $siteAccessToken = (string) $connection->site_access_token;

        if ($siteUrl === '' || $siteAccessToken === '') {
            throw new WooCommerceException('The WordPress plugin connection is not ready for imports.');
        }

        try {
            $response = Http::baseUrl($siteUrl)
                ->acceptJson()
                ->withHeaders([
                    'X-FooMake-Site-Token' => $siteAccessToken,
                ])
                ->get('/wp-json/foomake/v1/orders');
        } catch (ConnectionException $exception) {
            throw new WooCommerceException('The paired WordPress site could not be reached.', 0, $exception);
        }

        if ($response->failed()) {
            throw new WooCommerceException('The WordPress plugin order preview could not be loaded.');
        }

        $payload = $response->json();
        $rows = is_array($payload) && is_array($payload['data'] ?? null)
            ? $payload['data']
            : null;

        if (! is_array($rows)) {
            throw new WooCommerceException('The WordPress plugin order response was malformed.');
        }

        return array_values(array_map(
            fn (array $row): array => $this->normalizeRow($row),
            array_filter($rows, 'is_array')
        ));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $lines = is_array($row['lines'] ?? null) ? $row['lines'] : [];

        return [
            'external_id' => (string) ($row['external_id'] ?? ''),
            'external_source' => 'woocommerce',
            'external_status' => (string) ($row['external_status'] ?? ''),
            'date' => (string) ($row['date'] ?? ''),
            'customer' => $this->normalizeCustomer(is_array($row['customer'] ?? null) ? $row['customer'] : []),
            'lines' => array_values(array_map(
                fn (array $line): array => $this->normalizeLine($line),
                array_filter($lines, 'is_array')
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return array<string, string>
     */
    private function normalizeCustomer(array $customer): array
    {
        return [
            'external_id' => (string) ($customer['external_id'] ?? ''),
            'name' => (string) ($customer['name'] ?? ''),
            'email' => (string) ($customer['email'] ?? ''),
            'phone' => (string) ($customer['phone'] ?? ''),
            'address_line_1' => (string) ($customer['address_line_1'] ?? ''),
            'address_line_2' => (string) ($customer['address_line_2'] ?? ''),
            'city' => (string) ($customer['city'] ?? ''),
            'region' => (string) ($customer['region'] ?? ''),
            'postal_code' => (string) ($customer['postal_code'] ?? ''),
            'country_code' => (string) ($customer['country_code'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function normalizeLine(array $line): array
    {
        return [
            'external_id' => (string) ($line['external_id'] ?? ''),
            'product_external_id' => (string) ($line['product_external_id'] ?? ''),
            'name' => (string) ($line['name'] ?? ''),
            'quantity' => (string) ($line['quantity'] ?? '0.000000'),
            'unit_price_cents' => $line['unit_price_cents'] ?? null,
            'currency_code' => (string) ($line['currency_code'] ?? ''),
        ];
    }
}
