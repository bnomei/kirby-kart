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

class Shopify extends Provider
{
    protected string $name = ProviderEnum::SHOPIFY->value;

    public function checkout(): string
    {
        // NOTE: webhook-only integration; Shopify requires webhooks for checkout completion and disallows polling for status.
        return parent::checkout();
    }

    public function completed(array $data = []): array
    {
        // Shopify checkout completion should be handled via webhooks; there is no safe polling path here.
        $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.session_id');

        return parent::completed($data);
    }

    public function fetchProducts(): array
    {
        $products = [];
        $pageInfo = null;

        // REST Admin products listing
        while (true) {
            $remote = Remote::get($this->adminEndpoint().'/products.json', [
                'headers' => $this->adminHeaders(),
                'data' => array_filter([
                    'limit' => 250,
                    'page_info' => $pageInfo,
                    'fields' => 'id,title,body_html,tags,images,variants',
                ]),
            ]);

            $json = $remote->code() === 200 ? $remote->json() : null;
            if (! is_array($json)) {
                break;
            }

            foreach (A::get($json, 'products', []) as $product) {
                $products[A::get($product, 'id')] = $product;
            }

            // Shopify pagination via Link header
            $link = $remote->header('Link');
            if ($link && str_contains($link, 'rel="next"')) {
                preg_match('/page_info=([^&>]+)/', $link, $m);
                $pageInfo = $m[1] ?? null;
                if (! $pageInfo) {
                    break;
                }
            } else {
                break;
            }
        }

        return array_map(fn (array $data) => (new VirtualPage(
            $data,
            [
                'id' => fn ($i) => strval(A::get($i, 'id')),
                'title' => 'title',
                'content' => [
                    'description' => fn ($i) => strip_tags((string) A::get($i, 'body_html', '')),
                    'price' => fn ($i) => floatval(A::get($i, 'variants.0.price', 0)),
                    'tags' => fn ($i) => A::get($i, 'tags', ''),
                    'gallery' => fn ($i) => $this->findImagesFromUrls(array_map(
                        fn ($img) => A::get($img, 'src'), A::get($i, 'images', [])
                    )),
                    'variants' => function ($i) {
                        $variants = [];
                        foreach (A::get($i, 'variants', []) as $v) {
                            $variants[] = [
                                'price_id' => strval(A::get($v, 'id')),
                                'variant' => A::get($v, 'title'),
                                'price' => floatval(A::get($v, 'price', 0)),
                            ];
                        }

                        return empty($variants) ? null : $variants;
                    },
                ],
            ],
            $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), $products);
    }

    private function adminEndpoint(): string
    {
        $domain = rtrim(strval($this->option('store_domain')), '/');
        $version = strval($this->option('api_version') ?? '2024-07');

        return 'https://'.$domain.'/admin/api/'.$version;
    }

    private function adminHeaders(): array
    {
        return [
            'X-Shopify-Access-Token' => strval($this->option('admin_token')),
            'Accept' => 'application/json',
        ];
    }

    private function storefrontEndpoint(): string
    {
        $domain = rtrim(strval($this->option('store_domain')), '/');
        $version = strval($this->option('api_version') ?? '2024-07');

        return 'https://'.$domain.'/api/'.$version.'/graphql.json';
    }
}
