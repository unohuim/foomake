<?php

namespace App\Services;

use App\Integrations\WooCommerce\WooCommerceException;
use App\Models\WordPressPluginConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Fetch normalized WooCommerce customer preview rows through the paired WordPress plugin.
 */
class WordPressPluginCustomerPreviewService
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
                ->get('/wp-json/foomake/v1/customers');
        } catch (ConnectionException $exception) {
            throw new WooCommerceException('The paired WordPress site could not be reached.', 0, $exception);
        }

        if ($response->failed()) {
            throw new WooCommerceException('The WordPress plugin customer preview could not be loaded.');
        }

        $payload = $response->json();
        $rows = is_array($payload) && is_array($payload['data'] ?? null)
            ? $payload['data']
            : null;

        if (! is_array($rows)) {
            throw new WooCommerceException('The WordPress plugin customer response was malformed.');
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
        return [
            'external_id' => (string) ($row['external_id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'address_line_1' => (string) ($row['address_line_1'] ?? ''),
            'address_line_2' => (string) ($row['address_line_2'] ?? ''),
            'city' => (string) ($row['city'] ?? ''),
            'region' => (string) ($row['region'] ?? ''),
            'postal_code' => (string) ($row['postal_code'] ?? ''),
            'country_code' => (string) ($row['country_code'] ?? ''),
        ];
    }
}
