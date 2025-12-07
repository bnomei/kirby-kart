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
use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;
use Bnomei\Kart\VirtualPage;
use Bnomei\Kart\WebhookResult;
use Kirby\Http\Remote;
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

    public function checkout(): string
    {
        // NOTE: webhook-only integration; Kart expects FastSpring webhooks to finalize orders and does not initiate sessions here.
        return parent::checkout() ?? '/';
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }

    public function handleWebhook(array $payload, array $headers = []): WebhookResult
    {
        // TODO: implement FastSpring webhook parsing, signature verification, idempotency check, and mapping to orderData.
        return WebhookResult::ignored('FastSpring webhook handling not implemented yet.');
    }

    public function fetchProducts(): array
    {
        $products = [];

        // https://developer.fastspring.com/reference/list-all-product-paths
        $remote = Remote::get('https://api.fastspring.com/products', [
            'headers' => $this->headers(),
        ]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        if (! is_array($json)) {
            return [];
        }

        foreach (A::get($json, 'products', []) as $path) {
            // https://developer.fastspring.com/reference/retrieve-a-product
            $remote = Remote::get('https://api.fastspring.com/products/'.$path, [
                'headers' => $this->headers(),
            ]);

            $product = $remote->code() === 200 ? $remote->json() : null;
            if (! is_array($product)) {
                continue;
            }

            foreach (A::get($product, 'products') as $p) {
                $products[$p['product']] = $p;
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
