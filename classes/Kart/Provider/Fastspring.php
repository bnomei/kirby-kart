<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\ProductSyncException;
use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;
use Bnomei\Kart\VirtualPage;
use Bnomei\Kart\WebhookResult;
use Kirby\Toolkit\A;

class Fastspring extends Provider
{
    protected string $name = ProviderEnum::FASTSPRING->value;

    private function headers(): array
    {
        // https://developer.fastspring.com/reference/authentication
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic '.base64_encode(strval($this->option('username')).':'.strval($this->option('password'))),
        ];
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }

    public function handleWebhook(array $payload, array $headers = []): WebhookResult
    {
        // TODO: implement FastSpring webhook parsing, signature verification, idempotency check, and mapping to orderData.
        return WebhookResult::ignored('FastSpring webhook handling not implemented yet.');
    }

    public function fetchProducts(): array
    {
        $this->requireProductSyncOptions('username', 'password');
        $products = [];
        $options = ['headers' => $this->headers()];
        $url = 'https://api.fastspring.com/products';

        // https://developer.fastspring.com/reference/list-all-product-paths
        $json = $this->productSyncJson('products', fn () => $this->sendProductSyncRequest('GET', $url, $options), 'products');
        if (($json['result'] ?? 'success') !== 'success') {
            throw new ProductSyncException('FastSpring returned an invalid products response. Try syncing again.');
        }

        foreach ($json['products'] as $path) {
            if (! is_string($path) || $path === '') {
                throw new ProductSyncException('FastSpring returned an invalid product path. Try syncing again.');
            }

            // https://developer.fastspring.com/reference/retrieve-a-product
            $product = $this->productSyncJson('product details', fn () => $this->sendProductSyncRequest('GET', $url.'/'.$path, $options), 'products');
            if (($product['result'] ?? 'success') !== 'success' || $product['products'] === []) {
                throw new ProductSyncException('FastSpring returned an invalid product details response. Try syncing again.');
            }

            foreach ($product['products'] as $p) {
                $id = $p['product'] ?? null;
                if (! is_string($id) || $id === '' || ($p['result'] ?? 'success') !== 'success') {
                    throw new ProductSyncException('FastSpring returned an invalid product details response. Try syncing again.');
                }
                $products[$id] = $p;
            }
        }

        $currency = kart()->currency();

        return array_map(fn (array $data) =>
            // NOTE: changes here require a cache flush to take effect
        (new VirtualPage(
            $data,
            [
                // MAP: kirby <=> fastspring
                'id' => 'product', // id, uuid and slug will be hashed in ProductPage::create based on this `id`
                'title' => 'display.en',
                'content' => [
                    'created' => fn ($i) => date('Y-m-d H:i:s', time()),
                    'description' => 'description.summary.en',
                    'price' => fn ($i) => A::get($i, 'pricing.price.'.$currency, 0),
                    // tags
                    // categories
                    'gallery' => fn ($i) => $this->findImagesFromUrls(
                        A::get($i, 'image', '')
                    ),
                    // downloads
                ],
            ],
            $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), $products);
    }
}
