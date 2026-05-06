<?php

namespace App\Services;

use App\Integrations\WooCommerce\WooCommerceClient;
use App\Integrations\WooCommerce\WooCommerceException;
use App\Models\ExternalProductSourceConnection;

/**
 * Normalize WooCommerce products into the shared import-preview contract.
 */
class WooCommerceProductPreviewService
{
    public function __construct(
        private readonly WooCommerceClient $client
    ) {
    }

    /**
     * Verify a WooCommerce store connection before saving credentials.
     *
     * @throws WooCommerceException
     */
    public function verifyCredentials(string $storeUrl, string $consumerKey, string $consumerSecret): void
    {
        $this->client->verifyCredentials($storeUrl, $consumerKey, $consumerSecret);
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
        $products = $this->client->listProducts(
            (string) $connection->store_url,
            (string) $connection->consumer_key,
            (string) $connection->consumer_secret
        );

        $rows = [];

        foreach ($products as $product) {
            if (! is_array($product) || ! isset($product['id'], $product['name'], $product['type'])) {
                throw new WooCommerceException('The WooCommerce product response was malformed.');
            }

            $type = (string) $product['type'];

            if ($type === 'simple') {
                $rows[] = $this->normalizeSimpleProduct($product);
                continue;
            }

            if ($type !== 'variable') {
                continue;
            }

            $variations = $this->client->listVariations(
                (string) $connection->store_url,
                (string) $connection->consumer_key,
                (string) $connection->consumer_secret,
                (int) $product['id']
            );

            foreach ($variations as $variation) {
                if (! is_array($variation) || ! isset($variation['id'])) {
                    throw new WooCommerceException('The WooCommerce variation response was malformed.');
                }

                $rows[] = $this->normalizeVariation($product, $variation);
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function normalizeSimpleProduct(array $product): array
    {
        return [
            'external_id' => (string) $product['id'],
            'sku' => (string) ($product['sku'] ?? ''),
            'name' => (string) $product['name'],
            'price' => (string) ($product['price'] ?? ''),
            'is_active' => $this->isPublished($product['status'] ?? null),
            'is_sellable' => true,
            'is_manufacturable' => false,
            'is_purchasable' => false,
            'base_uom_id' => null,
            'product_type' => 'simple',
            'variation_attributes' => [],
        ];
    }

    /**
     * @param array<string, mixed> $parent
     * @param array<string, mixed> $variation
     * @return array<string, mixed>
     */
    private function normalizeVariation(array $parent, array $variation): array
    {
        $attributes = collect($variation['attributes'] ?? [])
            ->filter(fn (mixed $attribute): bool => is_array($attribute))
            ->map(function (array $attribute): array {
                return [
                    'name' => (string) ($attribute['name'] ?? ''),
                    'option' => (string) ($attribute['option'] ?? ''),
                ];
            })
            ->filter(fn (array $attribute): bool => $attribute['name'] !== '' && $attribute['option'] !== '')
            ->values();

        $suffix = $attributes->map(
            fn (array $attribute): string => $attribute['name'] . ': ' . $attribute['option']
        )->implode(' / ');

        $name = (string) $parent['name'];

        if ($suffix !== '') {
            $name .= ' - ' . $suffix;
        }

        return [
            'external_id' => (string) $variation['id'],
            'sku' => (string) ($variation['sku'] ?? ''),
            'name' => $name,
            'price' => (string) ($variation['price'] ?? ''),
            'is_active' => $this->isPublished($variation['status'] ?? null),
            'is_sellable' => true,
            'is_manufacturable' => false,
            'is_purchasable' => false,
            'base_uom_id' => null,
            'product_type' => 'variation',
            'parent_external_id' => (string) $parent['id'],
            'parent_name' => (string) $parent['name'],
            'variation_attributes' => $attributes->all(),
        ];
    }

    /**
     * Determine whether a WooCommerce status should be treated as active.
     */
    private function isPublished(mixed $status): bool
    {
        return $status === 'publish';
    }
}
