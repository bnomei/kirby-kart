<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\CartLine;
use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;
use Bnomei\Kart\Router;
use Bnomei\Kart\VirtualPage;
use Closure;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

class Polar extends Provider
{
    protected string $name = ProviderEnum::POLAR->value;

    public function checkout(): string
    {
        $options = $this->option('checkout_options', false);
        if ($options instanceof Closure) {
            $options = $options($this->kart);
        }

        $lineItem = $this->option('checkout_line', false);
        if ($lineItem instanceof Closure === false) {
            $lineItem = fn ($kart, $item) => [];
        }

        $products = array_merge(
            A::get($options, 'products', []),
            $this->kart->cart()->lines()->values(function (CartLine $line) use ($lineItem) {
                $raw = $line->product()?->raw()->yaml() ?? [];

                // prefer explicit one-time price that matches currency
                $priceId = null;
                foreach (A::get($raw, 'prices', []) as $price) {
                    if (A::get($price, 'type') === 'recurring') {
                        continue; // only one-time purchases
                    }
                    if (A::get($price, 'is_archived') === true) {
                        continue;
                    }
                    if (A::get($price, 'price_currency') && strtolower(A::get($price, 'price_currency')) !== strtolower($this->kart->currency())) {
                        continue;
                    }
                    $priceId = A::get($price, 'id');
                    break;
                }

                // fallback to the product id to let Polar pick the default catalog price
                return array_merge([
                    'product_id' => A::get($raw, 'id'),
                    'product_price_id' => $priceId,
                ], $lineItem($this->kart, $line));
            })
        );
        unset($options['products']);

        $payload = array_filter(array_merge([
            'products' => array_values(array_filter(array_map(fn ($p) => is_array($p) ? A::get($p, 'product_id') : $p, $products))),
            'success_url' => url(Router::PROVIDER_SUCCESS).'?checkout_id={CHECKOUT_ID}',
            'return_url' => url(Router::PROVIDER_CANCEL),
        ], $options));

        $remote = Remote::post($this->endpoint().'/checkouts', [
            'headers' => $this->headers(true),
            'data' => json_encode($payload),
        ]);

        $json = in_array($remote->code(), [200, 201]) ? $remote->json() : null;
        if (! is_array($json)) {
            throw new \Exception('Checkout failed', $remote->code());
        }

        $sessionId = A::get($json, 'id');
        if ($sessionId) {
            $this->kirby->session()->set('bnomei.kart.'.$this->name.'.session_id', $sessionId);
        }

        return parent::checkout() && $remote->code() === 200 ?
            A::get($json, 'url', '/') : '/';
    }

    public function fetchProducts(): array
    {
        $products = [];
        $page = 1;

        while (true) {
            $remote = Remote::get($this->endpoint().'/products', [
                'headers' => $this->headers(),
                'data' => array_filter([
                    'page' => $page,
                    'limit' => 100,
                    'is_archived' => 'false',
                    'is_recurring' => 'false', // one-time purchases only
                ]),
            ]);

            $json = $remote->code() === 200 ? $remote->json() : null;
            if (! is_array($json)) {
                break;
            }

            foreach (A::get($json, 'items', []) as $product) {
                $products[A::get($product, 'id')] = $product;
            }

            $maxPage = intval(A::get($json, 'pagination.max_page', 1));
            if ($page >= $maxPage) {
                break;
            }
            $page++;
        }

        return array_map(fn (array $data) =>
            (new VirtualPage(
                $data,
                [
                    'id' => 'id',
                    'title' => 'name',
                    'content' => [
                        'description' => 'description',
                        'price' => function ($i) {
                            $price = null;
                            foreach (A::get($i, 'prices', []) as $p) {
                                if (A::get($p, 'type') === 'recurring') {
                                    continue; // skip subscriptions
                                }
                                if (A::get($p, 'is_archived') === true) {
                                    continue;
                                }
                                if (A::get($p, 'price_amount') !== null) {
                                    $price = round(A::get($p, 'price_amount', 0) / 100.0, 2);
                                    break;
                                }
                            }

                            return $price;
                        },
                        'tags' => fn ($i) => A::get($i, 'metadata.tags', ''),
                        'categories' => fn ($i) => A::get($i, 'metadata.categories', ''),
                        'gallery' => fn ($i) => $this->findImagesFromUrls(array_filter(array_map(
                            fn ($m) => A::get($m, 'public_url'), A::get($i, 'medias', [])
                        ))),
                        'downloads' => fn ($i) => $this->findFilesFromUrls(
                            A::get($i, 'metadata.downloads', [])
                        ),
                    ],
                ],
                $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), $products);
    }

    private function endpoint(): string
    {
        $endpoint = strval($this->option('endpoint'));

        return rtrim($endpoint ?: 'https://api.polar.sh/v1', '/');
    }

    private function headers(bool $json = false): array
    {
        $headers = [
            'Authorization' => 'Bearer '.strval($this->option('access_token')),
            'Accept' => 'application/json',
        ];

        if ($json) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }
}
