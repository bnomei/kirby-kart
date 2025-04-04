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
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

class Paddle extends Provider
{
    protected string $name = ProviderEnum::PADDLE->value;

    public function checkout(): string
    {
        return '/';
    }

    public function fetchProducts(): array
    {
        $products = [];
        $cursor = null;
        $endpoint = $this->option('endpoint');

        while (true) {
            // https://developer.paddle.com/api-reference/products/list-products
            $remote = Remote::get($endpoint.'/products', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.strval($this->option('secret_key')),
                ],
                'data' => array_filter([
                    'status' => 'active',
                    'after' => $cursor,
                    'per_page' => 200,
                    'include' => 'prices',
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
                $cursor = A::get($product, 'id');
                $products[$product['id']] = $product;
            }

            if (! A::get($json, 'meta.pagination.has_more')) {
                break;
            }
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
                    'description' => 'description',
                    'price' => function ($i) {
                        foreach (A::get($i, 'prices', []) as $price) {
                            if (A::get($price, 'status') !== 'active') {
                                continue;
                            }
                            if (A::get($price, 'unit_price.currency_code', '') !== $this->kart->currency()) {
                                continue;
                            }

                            return A::get($price, 'unit_price.amount', 0) / 100.0;
                        }

                        return null;
                    },
                    'tags' => fn ($i) => A::get($i, 'custom_data.tags', A::get($i, 'custom_data.tag', '')),
                    'categories' => fn ($i) => A::get($i, 'custom_data.categories', A::get($i, 'custom_data.category', '')),
                    'gallery' => fn ($i) => $this->findImagesFromUrls(
                        explode(',', A::get($i, 'image_url', A::get($i, 'custom_data.gallery', '')))
                    ),
                    'downloads' => fn ($i) => $this->findFilesFromUrls(
                        explode(',', A::get($i, 'custom_data.downloads', ''))
                    ),
                    // maxapo, could be read from price
                ],
            ],
            $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), $products);
    }

    public function portal(?string $returnUrl = null): ?string
    {
        $customer = $this->userData('customerId');
        if (! $customer) {
            return null;
        }

        $endpoint = $this->option('endpoint');

        // https://developer.paddle.com/api-reference/customer-portals/create-customer-portal-session
        $remote = Remote::post("$endpoint/customers/$customer/portal-sessions", [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
        ]);

        if ($remote->code() !== 200) {
            return null;
        }

        $json = $remote->json();

        return A::get($json, 'data.urls.general.overview');
    }
}
