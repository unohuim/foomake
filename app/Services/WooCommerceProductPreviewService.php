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
        return array_merge($this->basePreviewRow(), [
            'external_id' => (string) $product['id'],
            'sku' => (string) ($product['sku'] ?? ''),
            'name' => (string) $product['name'],
            'price' => (string) ($product['price'] ?? ''),
            'default_price_cents' => $this->resolvePriceCents($product),
            'image_url' => $this->resolvePrimaryImageUrl($product['images'] ?? null),
            'is_active' => $this->isPublished($product['status'] ?? null),
            'is_sellable' => true,
            'is_manufacturable' => false,
            'is_purchasable' => false,
            'base_uom_id' => null,
            'product_type' => 'simple',
            'variation_attributes' => [],
        ]);
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

        return array_merge($this->basePreviewRow(), [
            'external_id' => (string) $variation['id'],
            'sku' => (string) ($variation['sku'] ?? ''),
            'name' => $name,
            'price' => (string) ($variation['price'] ?? ''),
            'default_price_cents' => $this->resolvePriceCents($variation),
            'image_url' => $this->resolveVariationImageUrl($variation),
            'is_active' => $this->isPublished($variation['status'] ?? null),
            'is_sellable' => true,
            'is_manufacturable' => false,
            'is_purchasable' => false,
            'base_uom_id' => null,
            'product_type' => 'variation',
            'parent_external_id' => (string) $parent['id'],
            'parent_name' => (string) $parent['name'],
            'variation_attributes' => $attributes->all(),
        ]);
    }

    /**
     * Return the shared preview-row nullable field contract.
     *
     * @return array<string, mixed>
     */
    private function basePreviewRow(): array
    {
        return [
            'price' => '',
            'default_price_cents' => null,
            'image_url' => null,
        ];
    }

    /**
     * Determine whether a WooCommerce status should be treated as active.
     */
    private function isPublished(mixed $status): bool
    {
        return $status === 'publish';
    }

    /**
     * Resolve WooCommerce price data from Store API or wc/v3 Admin REST payloads.
     */
    private function resolvePriceCents(array $product): ?int
    {
        $storeApiPrice = $this->normalizeStoreApiPriceToCents($product['prices'] ?? null);

        if ($storeApiPrice !== null) {
            return $storeApiPrice;
        }

        return $this->normalizeDecimalPriceToCents($product['price'] ?? null);
    }

    /**
     * Normalize a WooCommerce Store API smallest-unit price to integer cents.
     */
    private function normalizeStoreApiPriceToCents(mixed $prices): ?int
    {
        if (! is_array($prices)) {
            return null;
        }

        $rawPrice = $prices['price'] ?? null;

        if (is_int($rawPrice)) {
            return $rawPrice >= 0 ? $rawPrice : null;
        }

        $normalized = trim((string) ($rawPrice ?? ''));

        if ($normalized === '' || ! preg_match('/^\d+$/', $normalized)) {
            return null;
        }

        return (int) $normalized;
    }

    /**
     * Normalize a WooCommerce decimal price string to integer cents without float math.
     */
    private function normalizeDecimalPriceToCents(mixed $price): ?int
    {
        $normalized = trim((string) ($price ?? ''));

        if ($normalized === '' || ! preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            return null;
        }

        if (! str_contains($normalized, '.')) {
            return ((int) $normalized) * 100;
        }

        [$whole, $decimal] = explode('.', $normalized, 2);
        $decimal = str_pad(substr($decimal, 0, 2), 2, '0');

        return (((int) $whole) * 100) + ((int) $decimal);
    }

    /**
     * Resolve the first available WooCommerce image URL from a product images array.
     */
    private function resolvePrimaryImageUrl(mixed $images): ?string
    {
        if (! is_array($images)) {
            return null;
        }

        foreach ($images as $image) {
            if (! is_array($image)) {
                continue;
            }

            $src = trim((string) ($image['src'] ?? ''));

            if ($src !== '') {
                return $src;
            }
        }

        return null;
    }

    /**
     * Resolve a variation image URL from Store API or wc/v3 payloads.
     */
    private function resolveVariationImageUrl(array $variation): ?string
    {
        $imagesUrl = $this->resolvePrimaryImageUrl($variation['images'] ?? null);

        if ($imagesUrl !== null) {
            return $imagesUrl;
        }

        $image = $variation['image'] ?? null;

        if (! is_array($image)) {
            return null;
        }

        $src = trim((string) ($image['src'] ?? ''));

        return $src === '' ? null : $src;
    }
}
