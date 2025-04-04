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
use Bnomei\Kart\Router;
use Bnomei\Kart\VirtualPage;
use Closure;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

class Lemonsqueeze extends Provider
{
    protected string $name = ProviderEnum::LEMONSQUEEZE->value;

    /* Checkout but no redirect
    public function checkout(): string
    {
        $product = $this->kart->cart()->lines()->first()?->product();

        return parent::checkout() && $product ?
            A::get($product->raw()->yaml(), 'buy_now_url') : '/';
    }
    */

    public function checkout(): string
    {
        $product = $this->kart->cart()->lines()->first()?->product();

        $options = $this->option('checkout_options', false);
        if ($options instanceof Closure) {
            $options = $options($this->kart);
        }

        // https://docs.lemonsqueezy.com/api/checkouts/create-checkout
        $remote = Remote::post('https://api.lemonsqueezy.com/v1/checkouts', [
            'headers' => [
                'Content-Type' => 'application/vnd.api+json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
            'data' => json_encode([
                'data' => [
                    'type' => 'checkouts',
                    'relationships' => [
                        'store' => [
                            'data' => [
                                'type' => 'stores',
                                'id' => $this->option('store_id'),
                            ],
                        ],
                        'variant' => [
                            'data' => [
                                'type' => 'variants',
                                'id' => A::get($product->raw()->yaml(), 'variants.0.id'),
                            ],
                        ],
                    ],
                    'attributes' => array_filter(array_merge([
                        'product_options' => [
                            'enabled_variants' => [A::get($product->raw()->yaml(), 'variants.0.id')],
                            'redirect_url' => url(Router::PROVIDER_SUCCESS),
                        ],
                        'checkout_data' => array_filter([
                            'email' => $this->kirby->user()?->email(),
                            'name' => $this->kirby->user()?->name()->value(),
                        ]),
                        'test_mode' => $this->kirby->environment()->isLocal(),
                        'expires_at' => date('c', time() + 60 * 60), // 1h
                    ], $options)),
                ],
            ]),
        ]);

        return parent::checkout() && in_array($remote->code(), [200, 201]) ?
            $remote->json()['data']['attributes']['url'] : '/';
    }

    public function completed(array $data = []): array
    {
        // TODO
        return [];
    }

    public function fetchProducts(): array
    {
        $products = [];
        $page = 1;

        while (true) {
            // https://docs.lemonsqueezy.com/api/products/list-all-products
            $remote = Remote::get('https://api.lemonsqueezy.com/v1/products', [
                'headers' => [
                    'Accept' => 'application/vnd.api+json',
                    'Content-Type' => 'application/vnd.api+json',
                    'Authorization' => 'Bearer '.strval($this->option('secret_key')),
                ],
                'data' => array_filter([
                    'filter[store_id]' => $this->option('store_id'),
                    'page[number]' => $page,
                ]),
            ]);

            if ($remote->code() !== 200) {
                break;
            }

            $json = $remote->json();
            if (! is_array($json)) {
                break;
            }

            foreach (A::get($json, 'data') as $product) {
                if (A::get($product, 'attributes.status') !== 'published') {
                    continue;
                }
                $variants = [];
                // https://docs.lemonsqueezy.com/api/variants/list-all-variants
                $remote = Remote::get('https://api.lemonsqueezy.com/v1/variants', [
                    'headers' => [
                        'Accept' => 'application/vnd.api+json',
                        'Content-Type' => 'application/vnd.api+json',
                        'Authorization' => 'Bearer '.strval($this->option('secret_key')),
                    ],
                    'data' => array_filter([
                        'filter[product_id]' => $product['id'],
                    ]),
                ]);
                if ($remote->code() === 200) {
                    foreach (A::get($remote->json(), 'data', []) as $variant) {
                        $variants[] = $variant['attributes'] + ['id' => $variant['id']];
                    }
                }

                $products[$product['id']] = $product['attributes'] + [
                    'id' => $product['id'],
                    'variants' => $variants,
                ];
            }

            if (A::get($json, 'meta.page.lastPage') >= $page) {
                break;
            }
            $page++;
        }

        return array_map(fn (array $data) => // NOTE: changes here require a cache flush to take effect
        (new VirtualPage(
            $data,
            [
                // MAP: kirby <=> stripe
                'id' => 'id', // id, uuid and slug will be hashed in ProductPage::create based on this `id`
                'title' => 'name',
                'content' => [
                    'created' => fn ($i) => date('Y-m-d H:i:s', strtotime($i['created_at'])),
                    'description' => fn ($i) => Str::unhtml(A::get($i, 'description', '')),
                    'price' => fn ($i) => A::get($i, 'price', 0) / 100.0,
                    // 'tags' => fn ($i) => A::get($i, 'metadata.tags', []),
                    // 'categories' => fn ($i) => A::get($i, 'metadata.categories', []),
                    'gallery' => fn ($i) => $this->findImagesFromUrls(
                        A::get($i, 'large_thumb_url', A::get($i, 'thumb_url', []))
                    ),
                    // 'downloads' => fn ($i) => $this->findFilesFromUrls(
                    //     A::get($i, 'metadata.downloads', [])
                    // ),
                ],
            ],
            $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), $products);
    }
}
