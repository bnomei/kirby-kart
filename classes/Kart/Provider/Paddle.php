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

        $endpoint = $this->option('endpoint');

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
                'items' => $this->kart->cart()->lines()->values(function (CartLine $l) {
                    $price_id = null;
                    foreach (A::get($l->product()?->raw()->yaml(), 'prices', []) as $price) {
                        if (A::get($price, 'status') !== 'active') {
                            continue;
                        }
                        if (A::get($price, 'unit_price.currency_code', '') !== $this->kart->currency()) {
                            continue;
                        }

                        $price_id = $price['id'];
                        break; // first active EUR price
                    }

                    return [
                        'price_id' => $price_id, // @phpstan-ignore-line
                        'quantity' => $l->quantity(),
                    ];
                }),
            ], $options))),
        ]);

        if (! in_array($remote->code(), [200, 201])) {
            return '/';
        }

        $session_id = $remote->json()['data']['id']; // txn_...
        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.session_id', $session_id);

        return parent::checkout() ? Router::provider_payment([
            '_ptxn' => $session_id,
        ]) : '/';
    }

    public function completed(array $data = []): array
    {
        // get session from current session id param
        $sessionId = get('session_id');
        if (! $sessionId || ! is_string($sessionId)) {
            return [];
        }

        $endpoint = $this->option('endpoint');

        // https://developer.paddle.com/api-reference/transactions/get-transaction
        $remote = Remote::get($endpoint.'/transactions/'.$sessionId, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
            'data' => [
                'include' => [
                    'customer',
                ],
            ]]);
        if ($remote->code() !== 200) {
            return [];
        }

        $json = $remote->json();

        $invoice_url = null;
        // https://developer.paddle.com/api-reference/transactions/get-invoice-pdf
        $remote = Remote::get($endpoint.'/transactions/'.$sessionId.'/invoice', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
        ]);
        if ($remote->code() === 200) {
            $invoice_url = A::get($remote->json(), 'data.url');
        }

        $customer = [];
        // https://developer.paddle.com/api-reference/customers/get-customer
        $remote = Remote::get($endpoint.'/customers/'.A::get($json, 'data.customer_id'), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
        ]);
        if ($remote->code() === 200) {
            $customer = A::get($remote->json(), 'data');
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
                'quantity' => A::get($line, 'quantity'),
                'price' => round(A::get($price, 'unit_price.amount', 0) / 100.0, 2),
                // these values include the multiplication with quantity
                'total' => round(A::get($line, 'totals.total', 0) / 100.0, 2),
                'subtotal' => round(A::get($line, 'totals.subtotal', 0) / 100.0, 2),
                'tax' => round(A::get($line, 'totals.tax', 0) / 100.0, 2),
                'discount' => round(A::get($line, 'totals.discount', 0) / 100.0, 2),
            ];
        }

        return parent::completed($data);
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

                            // first active EUR price
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
