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

class Paypal extends Provider
{
    protected string $name = ProviderEnum::PAYPAL->value;

    protected ?string $token = null;

    private function token(): ?string
    {
        if ($this->token) {
            return $this->token;
        }

        // https://developer.paypal.com/api/rest/authentication/
        $endpoint = $this->option('endpoint');
        $remote = Remote::post($endpoint.'/v1/oauth2/token?grant_type=client_credentials', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic '.base64_encode(strval($this->option('client_id').':'.strval($this->option('client_secret')))),
            ],
        ]);
        if ($remote->code() === 200) {
            $this->token = $remote->json()['access_token'];
        }

        return $this->token;
    }

    private function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Prefer' => 'return=representation',
            'Authorization' => 'Bearer '.$this->token(),
        ];
    }

    public function checkout(): string
    {
        $options = $this->option('checkout_options', false);
        if ($options instanceof Closure) {
            $options = $options($this->kart);
        }

        $endpoint = $this->option('endpoint');
        $currency = $this->kart->option('currency');

        $uuid = kart()->option('orders.order.uuid');
        if ($uuid instanceof Closure) {
            $uuid = $uuid();
        }

        $lineItem = $this->option('checkout_line', false);
        if ($lineItem instanceof Closure === false) {
            $lineItem = fn ($kart, $item) => [];
        }

        // https://developer.paypal.com/docs/api/orders/v2/#orders_create
        $remote = Remote::post($endpoint.'/v2/checkout/orders', [
            'headers' => $this->headers(),
            'data' => json_encode(array_filter(array_merge([
                'intent' => 'CAPTURE',
                'payee' => ['email_address' => $this->kirby->user()?->email()],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                            'landing_page' => 'LOGIN',
                            'shipping_preference' => 'GET_FROM_FILE',
                            'user_action' => 'PAY_NOW',
                            'return_url' => url(Router::PROVIDER_SUCCESS),
                            'cancel_url' => url(Router::PROVIDER_CANCEL),
                        ],
                    ],
                ],
                'purchase_units' => [
                    [
                        'custom_id' => strtoupper($uuid),
                        // 'invoice_id' => strtoupper($uuid), // TODO: get invnum for next? locking?
                        'amount' => [
                            'currency_code' => $currency,
                            'value' => number_format($this->kart->cart()->subtotal(), 2),
                            // https://developer.paypal.com/docs/api/orders/v2/#orders_create!ct=application/json&path=purchase_units/amount/breakdown&t=request
                            'breakdown' => [
                                'item_total' => [
                                    'currency_code' => $currency,
                                    'value' => number_format($this->kart->cart()->subtotal(), 2),
                                ],
                                'tax_total' => [
                                    'currency_code' => $currency,
                                    'value' => number_format(0, 2),
                                ],
                                'discount' => [
                                    'currency_code' => $currency,
                                    'value' => number_format(0, 2),
                                ],
                                // 'shipping' => [],
                            ],
                        ],
                        'items' => $this->kart->cart()->lines()->values(fn (CartLine $l) => array_merge([
                            'sku' => $l->product()?->uuid()->id(), // used on completed again to find the product
                            'name' => $l->product()?->title()->value(),
                            'description' => $l->product()?->description()->value(),
                            // 'type' => A::get($l->product()?->raw()->yaml(), 'type'),
                            // 'category' => A::get($l->product()?->raw()->yaml(), 'category'),
                            'unit_amount' => [
                                'currency_code' => $currency,
                                'value' => number_format($l->product()?->price()->toFloat(), 2),
                            ],
                            'image_url' => A::get($l->product()?->raw()->yaml(), 'image_url', $l->product()?->firstGalleryImageUrl()),
                            'url' => $l->product()?->url(),
                            'quantity' => $l->quantity(),
                        ], $lineItem($this->kart, $l))),
                    ],
                ],
            ], $options))),
        ]);

        if ($remote->code() === 200) {
            $this->kirby->session()->set('kart.paypal.order.id', $remote->json()['id']);
            $this->kirby->session()->set('kart.paypal.cart.hash', $this->kart->cart()->hash());
        }

        if (! in_array($remote->code(), [200, 201])) {
            throw new \Exception('Checkout failed', $remote->code());
        }

        // https://www.sandbox.paypal.com/checkoutnow?token=...
        return parent::checkout() && $remote->code() === 200 ?
            $remote->json()['links'][1]['href'] : '/';
    }

    public function completed(array $data = []): array
    {
        // get session from current session id param
        $sessionId = $this->kirby->session()->get('kart.paypal.order.id');
        if (! is_string($sessionId)) {
            return [];
        }

        $endpoint = $this->option('endpoint');
        // https://developer.paypal.com/docs/api/orders/v2/#orders_get
        $remote = Remote::get($endpoint.'/v2/checkout/orders/'.$sessionId, [
            'headers' => $this->headers(),
        ]);
        if ($remote->code() !== 200) {
            return [];
        }

        $json = $remote->json();

        $data = array_merge($data, array_filter([
            // 'session_id' => $sessionId,
            'uuid' => A::get($json, 'purchase_units.0.custom_id'),
            'email' => A::get($json, 'payer.email_address'),
            'customer' => [
                'id' => A::get($json, 'payer.payer_id'),
                'email' => A::get($json, 'payer.email'),
                'name' => A::get($json, 'payer.given_name').' '.A::get($json, 'payer.surname'),
            ],
            'paidDate' => date('Y-m-d H:i:s', strtotime(A::get($json, 'create_time'))),
            'paymentMethod' => implode(',', array_keys(A::get($json, 'payment_source', []))),
            'paymentComplete' => A::get($json, 'status') === 'APPROVED',
            // 'invoiceurl' => A::get($json, 'invoice'),
            'paymentId' => A::get($json, 'id'),
        ]));

        $json = $remote->json();
        $uuid = kart()->option('products.product.uuid');
        if ($uuid instanceof Closure === false) {
            return [];
        }

        foreach (A::get($json, 'purchase_units.0.items') as $line) {
            $data['items'][] = [
                'key' => ['page://'.A::get($line, 'sku')],  // pages field expect an array
                'quantity' => intval(A::get($line, 'quantity')),
                'price' => round(floatval(A::get($line, 'unit_amount.value', 0)), 2),
                // these values include the multiplication with quantity
                'total' => round(floatval(A::get($line, 'unit_amount.value', 0)), 2) * intval(A::get($line, 'quantity')) + round(floatval(A::get($line, 'tax.value', 0)), 2),
                'subtotal' => round(floatval(A::get($line, 'unit_amount.value', 0)), 2) * intval(A::get($line, 'quantity')),
                'tax' => round(floatval(A::get($line, 'tax.value', 0)), 2),
                'discount' => 0, // NOTE: paypal has no discount per item
            ];
        }
        // TODO: maybe add a line without a product linked if a global discount was set

        return parent::completed($data);
    }

    public function fetchProducts(): array
    {
        $products = [];
        $page = 1;
        $endpoint = $this->option('endpoint');

        while (true) {
            // https://developer.paypal.com/docs/api/catalog-products/v1/#products_list
            $remote = Remote::get("$endpoint/v1/catalogs/products?page_size=20&page=$page&total_required=true", [
                'headers' => $this->headers(),
            ]);

            if ($remote->code() !== 200) {
                break;
            }

            $json = $remote->json();
            if (! is_array($json)) {
                break;
            }

            foreach (A::get($json, 'products') as $product) {
                $remote = Remote::get($product['links'][0]['href'], [
                    'headers' => $this->headers(),
                ]);

                if ($remote->code() === 200) {
                    $products[$product['id']] = $remote->json();
                }
            }

            if ($page >= intval(A::get($json, 'total_pages'))) {
                break;
            }
            $page++;
        }

        return array_map(fn (array $data) => // NOTE: changes here require a cache flush to take effect
        (new VirtualPage(
            $data,
            [
                // MAP: kirby <=> paypal
                'id' => 'id', // id, uuid and slug will be hashed in ProductPage::create based on this `id`
                'title' => 'name',
                'content' => [
                    'created' => fn ($i) => date('Y-m-d H:i:s', strtotime($i['create_time'])),
                    'description' => 'description',
                    // tags
                    // category
                    // price
                    'gallery' => fn ($i) => $this->findImagesFromUrls(
                        A::get($i, 'image_url', [])
                    ),
                    // downloads
                ],
            ],
            $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), $products);
    }
}
