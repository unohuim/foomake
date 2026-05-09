<?php

namespace App\Integrations\WooCommerce;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Perform raw WooCommerce REST API requests.
 */
class WooCommerceClient
{
    /**
     * Verify that the provided credentials can read WooCommerce products.
     *
     * @throws WooCommerceException
     */
    public function verifyCredentials(string $storeUrl, string $consumerKey, string $consumerSecret): void
    {
        try {
            $response = $this->baseRequest($storeUrl, $consumerKey, $consumerSecret)
                ->get('/products', [
                    'per_page' => 1,
                ]);
        } catch (ConnectionException $exception) {
            throw new WooCommerceException('The WooCommerce store could not be reached.', 0, $exception);
        }

        if ($response->failed()) {
            throw new WooCommerceException('The WooCommerce credentials could not be verified.');
        }

        if (! is_array($response->json())) {
            throw new WooCommerceException('The WooCommerce verification response was malformed.');
        }
    }

    /**
     * Fetch WooCommerce products for preview normalization.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws WooCommerceException
     */
    public function listProducts(string $storeUrl, string $consumerKey, string $consumerSecret): array
    {
        try {
            $response = $this->baseRequest($storeUrl, $consumerKey, $consumerSecret)
                ->get('/products', [
                    'per_page' => 100,
                ]);
        } catch (ConnectionException $exception) {
            throw new WooCommerceException('The WooCommerce store could not be reached.', 0, $exception);
        }

        if ($response->failed()) {
            throw new WooCommerceException('The WooCommerce product preview could not be loaded.');
        }

        $products = $response->json();

        if (! is_array($products)) {
            throw new WooCommerceException('The WooCommerce product response was malformed.');
        }

        return $products;
    }

    /**
     * Fetch WooCommerce customers for preview normalization.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws WooCommerceException
     */
    public function listCustomers(string $storeUrl, string $consumerKey, string $consumerSecret): array
    {
        try {
            $response = $this->baseRequest($storeUrl, $consumerKey, $consumerSecret)
                ->get('/customers', [
                    'per_page' => 100,
                ]);
        } catch (ConnectionException $exception) {
            throw new WooCommerceException('The WooCommerce store could not be reached.', 0, $exception);
        }

        if ($response->failed()) {
            throw new WooCommerceException('The WooCommerce customer preview could not be loaded.');
        }

        $customers = $response->json();

        if (! is_array($customers)) {
            throw new WooCommerceException('The WooCommerce customer response was malformed.');
        }

        return $customers;
    }

    /**
     * Fetch WooCommerce variations for a parent variable product.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws WooCommerceException
     */
    public function listVariations(
        string $storeUrl,
        string $consumerKey,
        string $consumerSecret,
        int $productId
    ): array {
        try {
            $response = $this->baseRequest($storeUrl, $consumerKey, $consumerSecret)
                ->get("/products/{$productId}/variations", [
                    'per_page' => 100,
                ]);
        } catch (ConnectionException $exception) {
            throw new WooCommerceException('The WooCommerce store could not be reached.', 0, $exception);
        }

        if ($response->failed()) {
            throw new WooCommerceException('The WooCommerce variation preview could not be loaded.');
        }

        $variations = $response->json();

        if (! is_array($variations)) {
            throw new WooCommerceException('The WooCommerce variation response was malformed.');
        }

        return $variations;
    }

    /**
     * Build a normalized WooCommerce request.
     */
    private function baseRequest(string $storeUrl, string $consumerKey, string $consumerSecret)
    {
        $normalizedStoreUrl = rtrim($storeUrl, '/');

        return Http::baseUrl($normalizedStoreUrl . '/wp-json/wc/v3')
            ->acceptJson()
            ->withBasicAuth($consumerKey, $consumerSecret);
    }
}
