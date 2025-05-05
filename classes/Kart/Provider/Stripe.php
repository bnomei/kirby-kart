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

class Stripe extends Provider
{
    protected string $name = ProviderEnum::STRIPE->value;

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

        // https://docs.stripe.com/api/checkout/sessions/create?lang=curl
        $remote = Remote::post('https://api.stripe.com/v1/checkout/sessions', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
            'data' => array_filter(array_merge([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'currency' => strtolower($this->kart->currency()),
                'customer_email' => $this->kirby->user()?->email(),
                'success_url' => url(Router::PROVIDER_SUCCESS).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => url(Router::PROVIDER_CANCEL),
                'invoice_creation' => ['enabled' => 'true'],
                'line_items' => $this->kart->cart()->lines()->values(fn (CartLine $l) => array_merge([
                    'price' => $l->variant() ?
                            $l->product()?->priceWithVariant($l->variant(), true) :
                            A::get($l->product()?->raw()->yaml(), 'default_price.id'), // @phpstan-ignore-line
                    'quantity' => $l->quantity(),
                ], $lineItem($this->kart, $l))),
            ], $options)),
        ]);

        $json = in_array($remote->code(), [200, 201]) ? $remote->json() : null;
        if (! is_array($json)) {
            throw new \Exception('Checkout failed', $remote->code());
        }

        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.session_id', A::get($json, 'id'));

        return parent::checkout() && $remote->code() === 200 ?
            A::get($json, 'url') : '/';
    }

    public function completed(array $data = []): array
    {
        // get session from current session id param
        $sessionId = get('session_id');
        if (! $sessionId || ! is_string($sessionId) || $sessionId !== $this->kirby->session()->get('bnomei.kart.'.$this->name.'.session_id')) {
            return [];
        }

        $remote = Remote::get('https://api.stripe.com/v1/checkout/sessions/'.$sessionId, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
            'data' => [
                'expand' => [
                    'customer',
                ],
            ]]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        if (! is_array($json)) {
            return [];
        }

        $data = array_merge($data, array_filter([
            // 'session_id' => $sessionId,
            'email' => A::get($json, 'customer_email'),
            'customer' => [
                'id' => A::get($json, 'customer.id'),
                'email' => A::get($json, 'customer.email'),
                'name' => A::get($json, 'customer.name'),
            ],
            'paidDate' => date('Y-m-d H:i:s', A::get($json, 'created', time())),
            'paymentMethod' => implode(',', A::get($json, 'payment_method_types', [])),
            'paymentComplete' => A::get($json, 'payment_status') === 'paid',
            'invoiceurl' => A::get($json, 'invoice'),
            'paymentId' => A::get($json, 'id'),
        ]));

        $remote = Remote::get('https://api.stripe.com/v1/checkout/sessions/'.$sessionId.'/line_items', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ], [
                'limit' => 100, // is max without pagination. $this->kart->cart()->lines()->count(),
            ]]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        if (! is_array($json)) {
            return [];
        }

        $uuid = kart()->option('products.product.uuid');
        if ($uuid instanceof Closure === false) {
            return [];
        }

        /** @var \Closure $likey */
        $likey = kart()->option('licenses.license.uuid');

        // https://docs.stripe.com/api/checkout/sessions/line_items
        foreach (A::get($json, 'data') as $line) {
            $data['items'][] = [
                'key' => ['page://'.$uuid(null, ['id' => A::get($line, 'price.product')])],  // pages field expect an array
                'variant' => A::get($line, 'price.metadata.variant', ''),
                'quantity' => A::get($line, 'quantity'),
                'price' => round(A::get($line, 'price.unit_amount', 0) / 100.0, 2),
                // these values include the multiplication with quantity
                'total' => round(A::get($line, 'amount_total', 0) / 100.0, 2),
                'subtotal' => round(A::get($line, 'amount_subtotal', 0) / 100.0, 2),
                'tax' => round(A::get($line, 'amount_tax', 0) / 100.0, 2),
                'discount' => round(A::get($line, 'amount_discount', 0) / 100.0, 2),
                'licensekey' => $likey($data + $json + ['line' => $line]),
            ];
        }

        $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.session_id');

        return parent::completed($data);
    }

    public function fetchProducts(): array
    {
        $products = [];
        $cursor = null;

        while (true) {
            // https://docs.stripe.com/api/products/list?lang=curl
            $remote = Remote::get('https://api.stripe.com/v1/products', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.strval($this->option('secret_key')),
                ],
                'data' => array_filter([
                    'active' => 'true',
                    'limit' => 100,
                    'starting_after' => $cursor,
                    'expand' => ['data.default_price'],
                ]),
            ]);

            $json = $remote->code() === 200 ? $remote->json() : null;
            if (! is_array($json)) {
                break;
            }

            foreach (A::get($json, 'data') as $product) {
                $cursor = A::get($product, 'id');
                $products[$cursor] = $product;

                // https://docs.stripe.com/api/prices/list?lang=curl
                $remote = Remote::get('https://api.stripe.com/v1/prices', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer '.strval($this->option('secret_key')),
                    ],
                    'data' => array_filter([
                        'product' => $cursor,
                        'active' => 'true',
                        'limit' => 100,
                        'currency' => strtolower($this->kart->currency()),
                    ]),
                ]);

                $productPrices = $remote->code() === 200 ? $remote->json() : null;
                if (is_array($productPrices)) {
                    $products[$cursor]['prices'] = A::get($productPrices, 'data', []);
                }
            }

            if (! A::get($json, 'has_more')) {
                break;
            }
        }

        return array_map(fn (array $data) =>
            // NOTE: changes here require a cache flush to take effect
            (new VirtualPage(
                $data,
                [
                    // MAP: kirby <=> stripe
                    'id' => 'id', // id, uuid and slug will be hashed in ProductPage::create based on this `id`
                    'title' => 'name',
                    'content' => [
                        'created' => fn ($i) => date('Y-m-d H:i:s', $i['created']),
                        'description' => 'description',
                        'price' => fn ($i) => A::get($i, 'default_price.unit_amount', 0) / 100.0,
                        'tags' => fn ($i) => A::get($i, 'metadata.tags', A::get($i, 'metadata.tag', '')),
                        'categories' => fn ($i) => A::get($i, 'metadata.categories', A::get($i, 'metadata.category', '')),
                        'gallery' => fn ($i) => $this->findImagesFromUrls(
                            A::get($i, 'images', A::get($i, 'metadata.gallery', []))
                        ),
                        'downloads' => fn ($i) => $this->findFilesFromUrls(
                            A::get($i, 'metadata.downloads', [])
                        ),
                        'variants' => function ($i) {
                            $variants = [];
                            foreach (A::get($i, 'prices', []) as $price) {
                                $variants[] = [
                                    'price_id' => $price['id'],
                                    'variant' => A::get($price, 'metadata.variant', ''),
                                    'price' => round(A::get($price, 'unit_amount', 0) / 100.0, 2),
                                    'image' => explode(',', A::get($price, 'metadata.image', '')),
                                ];
                            }

                            return empty($variants) ? null : $variants;
                        },
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

        $remote = Remote::get('https://api.stripe.com/v1/billing_portal/sessions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
            'data' => array_filter([
                'customer' => $customer,
                'return_url' => $returnUrl,
            ]),
        ]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        if (! is_array($json)) {
            return null;
        }

        return A::get($json, 'url');
    }
}
