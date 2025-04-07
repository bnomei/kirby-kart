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
use Bnomei\Kart\VirtualPage;
use Closure;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

class Fastspring extends Provider
{
    protected string $name = ProviderEnum::FASTSPRING->value;

    private function headers(): array
    {
        // https://docs.snipcart.com/v3/api-reference/authentication
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic '.base64_encode(strval($this->option('username')).':'.strval($this->option('password'))),
        ];
    }

    public function checkout(): string
    {
        $options = $this->option('checkout_options', false);
        if ($options instanceof Closure) {
            $options = $options($this->kart);
        }

        // https://developer.fastspring.com/reference/create-a-session
        $remote = Remote::post('https://api.fastspring.com/sessions', [
            'headers' => $this->headers(),
            'data' => json_encode(array_filter(array_merge([
                'account' => $this->kart->provider()->userData('customerId'), // TODO: required
                'items' => $this->kart->cart()->lines()->values(fn (CartLine $l) => [
                    'product' => A::get($l->product()?->raw()->yaml(), 'product', ''),
                    'quantity' => $l->quantity(),
                ]),
            ], $options))),
        ]);

        $session_id = null;

        if ($remote->code() === 200) {
            $session_id = $remote->json()['id'];
            $this->kirby->session()->set('kart.'.$this->name.'.session_id', $session_id);
        }

        //
        return parent::checkout() && $remote->code() === 200 && $session_id ?
            strval($this->option('store_url')).'/session/'.$session_id : '/';
    }

    public function fetchProducts(): array
    {
        $products = [];

        // https://developer.fastspring.com/reference/list-all-product-paths
        $remote = Remote::get('https://api.fastspring.com/products', [
            'headers' => $this->headers(),
        ]);

        if ($remote->code() !== 200) {
            return [];
        }

        foreach (A::get($remote->json(), 'products', []) as $path) {
            // https://developer.fastspring.com/reference/retrieve-a-product
            $remote = Remote::get('https://api.fastspring.com/products/'.$path, [
                'headers' => $this->headers(),
            ]);

            if ($remote->code() !== 200) {
                continue;
            }

            $json = $remote->json();
            if (! is_array($json)) {
                continue;
            }

            foreach (A::get($json, 'products') as $product) {
                $products[$product['product']] = $product;
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
