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

class Paddle extends Provider
{
    protected string $name = ProviderEnum::PADDLE->value;

    public function checkout(): string
    {
        $options = $this->option('checkout_options', false);
        if ($options instanceof Closure) {
            $options = $options($this->kart);
        }

        $endpoint = strval($this->option('endpoint'));
        $lineItem = $this->option('checkout_line', false);
        if ($lineItem instanceof Closure === false) {
            $lineItem = fn ($kart, $item) => [];
        }

        $lines = A::get($options, 'items', []);
        unset($options['items']);

        // https://developer.paddle.com/api-reference/transactions/create-transaction
        $remote = Remote::post($endpoint.'/transactions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
            'data' => json_encode(array_filter(array_merge([
                'collection_mode' => 'automatic',
                'customer_id' => $this->userData('customerId'),
                'currency_code' => $this->kart->currency(),
                'items' => array_merge($lines, $this->kart->cart()->lines()->values(function (CartLine $l) use ($lineItem) {
                    $price_id = null;
                    foreach (A::get($l->product()?->raw()->yaml(), 'prices', []) as $price) {
                        if (A::get($price, 'status') !== 'active') {
                            continue;
                        }
                        if (A::get($price, 'unit_price.currency_code', '') !== $this->kart->currency()) {
                            continue;
                        }

                        if (! $l->variant() || $l->variant() === A::get($price, 'custom_data.variant')) {
                            $price_id = $price['id'];
                            break; // match or first active EUR price
                        }
                    }

                    return array_merge([
                        'price_id' => $price_id,
                        'quantity' => $l->quantity(),
                    ], $lineItem($this->kart, $l));
                })),
            ], $options))),
        ]);

        $json = in_array($remote->code(), [200, 201]) ? $remote->json() : null;
        if (! is_array($json)) {
            throw new \Exception('Checkout failed', $remote->code());
        }

        $session_id = A::get($remote->json(), 'data.id'); // txn_...
        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.session_id', $session_id);

        return parent::checkout() ? Router::provider_payment([
            '_ptxn' => $session_id,
        ]) : '/';
    }

    public function completed(array $data = []): array
    {
        // get session from current session id param
        $sessionId = get('session_id');
        if (! $sessionId || ! is_string($sessionId) || $sessionId !== $this->kirby->session()->get('bnomei.kart.'.$this->name.'.session_id')) {
            return [];
        }

        $endpoint = strval($this->option('endpoint'));

        // https://developer.paddle.com/api-reference/transactions/get-transaction
        $remote = Remote::get($endpoint.'/transactions/'.$sessionId, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
            'data' => [
                'include' => implode(',', [
                    'customer',
                ]),
            ]]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        if (! is_array($json)) {
            return [];
        }

        $invoice_url = null;
        // https://developer.paddle.com/api-reference/transactions/get-transaction-invoice (short-lived PDF URL)
        $remote = Remote::get($endpoint.'/transactions/'.$sessionId.'/invoice', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
        ]);

        $inv = $remote->code() === 200 ? $remote->json() : null;
        if (is_array($inv)) {
            // TODO: valid ONLY for 1h
            $invoice_url = A::get($inv, 'data.url');
        }

        $customer = [];
        // https://developer.paddle.com/api-reference/customers/get-customer
        $remote = Remote::get($endpoint.'/customers/'.A::get($json, 'data.customer_id'), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
        ]);

        $cust = $remote->code() === 200 ? $remote->json() : null;
        if (is_array($cust)) {
            $customer = A::get($cust, 'data');
        }

        $data = array_merge($data, array_filter([
            // 'session_id' => $sessionId,
            'email' => A::get($customer, 'email'),
            'customer' => [
                'id' => A::get($customer, 'id'),
                'email' => A::get($customer, 'email'),
                'name' => A::get($customer, 'name'),
            ],
            'paidDate' => date('Y-m-d H:i:s', strtotime(A::get($json, 'data.created_at'))),
            // 'paymentMethod' => implode(',', A::get($json, 'payment_method_types', [])),
            'paymentComplete' => A::get($json, 'data.status') === 'completed',
            'invoiceurl' => $invoice_url,
            'paymentId' => A::get($json, 'data.id'),
        ]));

        $uuid = kart()->option('products.product.uuid');
        if ($uuid instanceof Closure === false) {
            return [];
        }

        /** @var \Closure $likey */
        $likey = kart()->option('licenses.license.uuid');

        foreach (A::get($json, 'data.details.line_items') as $line) {
            $price_id = A::get($line, 'price_id');
            $price = [];
            foreach (A::get($json, 'data.items', []) as $item) {
                if ($item['price']['id'] === $price_id) {
                    $price = $item['price'];
                    break;
                }
            }
            $data['items'][] = [
                'key' => ['page://'.$uuid(null, ['id' => A::get($price, 'product_id')])],  // pages field expect an array
                'variant' => A::get($price, 'custom_data.variant', ''),
                'quantity' => A::get($line, 'quantity'),
                'price' => round(A::get($price, 'unit_price.amount', 0) / 100.0, 2),
                // these values include the multiplication with quantity
                'total' => round(A::get($line, 'totals.total', 0) / 100.0, 2),
                'subtotal' => round(A::get($line, 'totals.subtotal', 0) / 100.0, 2),
                'tax' => round(A::get($line, 'totals.tax', 0) / 100.0, 2),
                'discount' => round(A::get($line, 'totals.discount', 0) / 100.0, 2),
                'licensekey' => $likey($data + ['line' => $line] + ['price' => $price]),
            ];
        }

        $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.session_id');

        return parent::completed($data);
    }

    public function fetchProducts(): array
    {
        $products = [];
        $cursor = null;
        $endpoint = strval($this->option('endpoint'));

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
                    'include' => implode(',', ['prices']),
                ]),
            ]);

            $json = $remote->code() === 200 ? $remote->json() : null;
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

                            // first active EUR price
                            return A::get($price, 'unit_price.amount', 0) / 100.0;
                        }

                        return null;
                    },
                    'tags' => fn ($i) => A::get($i, 'custom_data.tags', A::get($i, 'custom_data.tag', '')),
                    'categories' => fn ($i) => A::get($i, 'custom_data.categories', A::get($i, 'custom_data.category', '')),
                    'featured' => fn ($i) => boolval(filter_var(A::get($i, 'custom_data.featured'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false),
                    'gallery' => fn ($i) => $this->findImagesFromUrls(
                        explode(',', A::get($i, 'image_url', A::get($i, 'custom_data.gallery', '')))
                    ),
                    'downloads' => fn ($i) => $this->findFilesFromUrls(
                        explode(',', A::get($i, 'custom_data.downloads', ''))
                    ),
                    // maxapo, could be read from price
                    'variants' => function ($i) {
                        $variants = [];
                        foreach (A::get($i, 'prices', []) as $price) {
                            $v = A::get($price, 'custom_data.variant', '');
                            if (! $v || empty(trim($v))) {
                                continue;
                            }
                            if (A::get($price, 'status') !== 'active') {
                                continue;
                            }
                            if (A::get($price, 'unit_price.currency_code', '') !== $this->kart->currency()) {
                                continue;
                            }

                            $variants[] = [
                                'price_id' => $price['id'],
                                'variant' => $v,
                                'price' => round(A::get($price, 'unit_price.amount', 0) / 100.0, 2),
                                'image' => explode(',', A::get($price, 'custom_data.image', '')),
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
        if (! is_string($customer)) {
            return null;
        }

        $endpoint = strval($this->option('endpoint'));

        // https://developer.paddle.com/api-reference/customer-portal/create-customer-portal-session
        $remote = Remote::post("$endpoint/customers/$customer/portal-sessions", [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
        ]);

        $json = in_array($remote->code(), [200, 201]) ? $remote->json() : null;
        if (! is_array($json)) {
            return null;
        }

        return A::get($json, 'data.urls.general.overview') ?? A::get($json, 'data.url');
    }
}
