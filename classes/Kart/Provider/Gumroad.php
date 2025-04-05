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

class Gumroad extends Provider
{
    protected string $name = ProviderEnum::GUMROAD->value;

    public function checkout(): string
    {
        $product = $this->kart->cart()->lines()->first()?->product();

        return parent::checkout() && $product ?
            A::get($product->raw()->yaml(), 'short_url') : '/';
    }

    public function fetchProducts(): array
    {
        $products = [];

        // https://gumroad.com/api#products
        $remote = Remote::get('https://api.gumroad.com/v2/products?access_token='.strval($this->option('access_token')), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);

        if ($remote->code() !== 200) {
            return [];
        }

        $json = $remote->json();
        if (! is_array($json)) {
            return [];
        }

        foreach (A::get($json, 'products') as $product) {
            if (! A::get($product, 'published')) {
                continue;
            }
            if (A::get($product, 'deleted')) {
                continue;
            }
            $products[$product['id']] = $product;
        }

        return array_map(fn (array $data) =>
            // NOTE: changes here require a cache flush to take effect
        (new VirtualPage(
            $data,
            [
                // MAP: kirby <=> gumroad
                'id' => 'id', // id, uuid and slug will be hashed in ProductPage::create based on this `id`
                'title' => 'name',
                'content' => [
                    'created' => fn ($i) => date('Y-m-d H:i:s', time()),
                    'description' => 'description',
                    'price' => fn ($i) => A::get($i, 'price', 0) / 100.0,
                    'tags' => fn ($i) => implode(',', A::get($i, 'tags', [])),
                    // categories
                    'gallery' => fn ($i) => $this->findImagesFromUrls(
                        A::get($i, 'thumbnail_url', [])
                    ),
                    // downloads
                    // 'maxapo' => 'max_purchase_count',
                ],
            ],
            $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), $products);
    }
}
