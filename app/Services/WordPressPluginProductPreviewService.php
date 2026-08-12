<?php

namespace App\Services;

use App\Integrations\WooCommerce\WooCommerceException;
use App\Models\WordPressPluginConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Fetch normalized WooCommerce product preview rows through the paired WordPress plugin.
 */
class WordPressPluginProductPreviewService
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
                ->get('/wp-json/foomake/v1/products');
        } catch (ConnectionException $exception) {
            throw new WooCommerceException('The paired WordPress site could not be reached.', 0, $exception);
        }

        if ($response->failed()) {
            throw new WooCommerceException('The WordPress plugin product preview could not be loaded.');
        }

        $payload = $response->json();
        $rows = is_array($payload) && is_array($payload['data'] ?? null)
            ? $payload['data']
            : null;

        if (! is_array($rows)) {
            throw new WooCommerceException('The WordPress plugin product response was malformed.');
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
            'external_source' => 'woocommerce',
            'sku' => (string) ($row['sku'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'price' => (string) ($row['price'] ?? ''),
            'default_price_cents' => $row['default_price_cents'] ?? null,
            'default_price_currency_code' => (string) ($row['default_price_currency_code'] ?? ''),
            'image_url' => $this->nullableString($row['image_url'] ?? null),
            'is_active' => (bool) ($row['is_active'] ?? true),
            'is_sellable' => true,
            'is_manufacturable' => (bool) ($row['is_manufacturable'] ?? false),
            'is_purchasable' => (bool) ($row['is_purchasable'] ?? false),
            'base_uom_id' => null,
            'product_type' => (string) ($row['product_type'] ?? 'simple'),
            'parent_external_id' => (string) ($row['parent_external_id'] ?? ''),
            'parent_name' => (string) ($row['parent_name'] ?? ''),
            'variation_attributes' => is_array($row['variation_attributes'] ?? null)
                ? $row['variation_attributes']
                : [],
        ];
    }

    /**
     * Normalize nullable strings from plugin payloads.
     */
    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }
}
