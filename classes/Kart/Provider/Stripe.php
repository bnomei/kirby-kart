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
use RuntimeException;

class Stripe extends Provider
{
    protected string $name = ProviderEnum::STRIPE->value;

    protected function canSyncProducts(): bool
    {
        return trim(strval($this->option('secret_key'))) !== '';
    }

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

        $contact = $this->checkoutContact();
        $shippingRate = $this->checkoutShippingRate();
        $shippingMethod = $this->checkoutShippingMethod();

        $lines = A::get($options, 'line_items', []);
        unset($options['line_items']);

        $shippingOptions = null;
        if (! array_key_exists('shipping_options', $options) && $shippingRate !== null) {
            $shippingOptions = [[
                'shipping_rate_data' => [
                    'display_name' => $shippingMethod ?: 'Shipping',
                    'type' => 'fixed_amount',
                    'fixed_amount' => [
                        'amount' => (int) round($shippingRate * 100),
                        'currency' => strtolower($this->kart->currency()),
                    ],
                ],
            ]];
        }

        // https://docs.stripe.com/api/checkout/sessions/create?lang=curl
        $remote = Remote::post('https://api.stripe.com/v1/checkout/sessions', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
            'data' => array_filter(array_merge([
                'mode' => 'payment', // NOTE: if price is a recurring price this must be a `subscription`
                'payment_method_types' => ['card'],
                'currency' => strtolower($this->kart->currency()),
                'customer_email' => $contact['email'] ?? $this->kirby->user()?->email(),
                'success_url' => url(Router::PROVIDER_SUCCESS).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => url(Router::PROVIDER_CANCEL),
                'invoice_creation' => ['enabled' => 'true'],
                'shipping_options' => $shippingOptions,
                'line_items' => array_merge($lines, $this->kart->cart()->lines()->values(fn (CartLine $l) => array_merge([
                    'price' => $l->variant() ?
                            $l->product()?->priceIdForVariant($l->variant()) :
                            A::get($l->product()?->raw()->yaml(), 'default_price.id'), // @phpstan-ignore-line
                    'quantity' => $l->quantity(),
                ], $lineItem($this->kart, $l)))),
            ], $options)),
        ]);

        $json = in_array($remote->code(), [200, 201]) ? $remote->json() : null;

        if (! is_array($json)) {
            throw new \Exception('Checkout failed', $remote->code());
        }

        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.session_id', A::get($json, 'id'));

        return parent::checkout() && in_array($remote->code(), [200, 201], true) ?
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
                    'payment_intent.latest_charge',
                    'payment_intent.payment_method',
                ],
            ]]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        if (! is_array($json)) {
            return [];
        }

        $paymentIntentStatus = A::get($json, 'payment_intent.status');
        $paymentMethod = A::get($json, 'payment_intent.latest_charge.payment_method_details.card.wallet.type') ??
            A::get($json, 'payment_intent.latest_charge.payment_method_details.type') ??
            A::get($json, 'payment_intent.payment_method.type');
        $invoiceId = A::get($json, 'invoice');
        $invoiceUrl = null;
        if (is_string($invoiceId) && $invoiceId !== '') {
            $remote = Remote::get('https://api.stripe.com/v1/invoices/'.$invoiceId, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.strval($this->option('secret_key')),
                ],
            ]);
            $invoiceJson = $remote->code() === 200 ? $remote->json() : null;
            if (is_array($invoiceJson)) {
                $invoiceUrl = A::get($invoiceJson, 'hosted_invoice_url') ?? A::get($invoiceJson, 'invoice_pdf');
            }
        }
        $invoiceValue = $invoiceUrl ?: $invoiceId;
        $data = array_merge($data, array_filter([
            // 'session_id' => $sessionId,
            'email' => A::get($json, 'customer_email'),
            'customer' => [
                'id' => A::get($json, 'customer.id'),
                'email' => A::get($json, 'customer.email'),
                'name' => A::get($json, 'customer.name'),
            ],
            'paidDate' => date('Y-m-d H:i:s', A::get($json, 'created', time())),
            'paymentMethod' => $paymentMethod,
            'paymentComplete' => A::get($json, 'payment_status') === 'paid' || $paymentIntentStatus === 'succeeded',
            'paymentAuthorized' => $paymentIntentStatus === 'requires_capture',
            'invoiceurl' => $invoiceValue,
            'paymentId' => A::get($json, 'payment_intent.id', A::get($json, 'id')),
        ]));

        $remote = Remote::get('https://api.stripe.com/v1/checkout/sessions/'.$sessionId.'/line_items', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
            'data' => [
                'limit' => 100, // is max without pagination. $this->kart->cart()->lines()->count(),
            ],
        ]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        if (! is_array($json)) {
            return [];
        }

        $uuid = kart()->option('products.product.uuid');
        if ($uuid instanceof Closure === false) {
            return [];
        }

        /** @var Closure $likey */
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
        if ($this->canSyncProducts() === false) {
            return [];
        }

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

            $json = $this->productResponseJson($remote, 'Stripe products');
            $items = A::get($json, 'data');
            if (! is_array($items)) {
                throw new RuntimeException('Malformed Stripe products list.');
            }

            $previousCursor = $cursor;
            foreach ($items as $product) {
                if (! is_array($product)) {
                    throw new RuntimeException('Malformed Stripe product list item.');
                }
                $id = A::get($product, 'id');
                if (! is_string($id) || trim($id) === '') {
                    throw new RuntimeException('Missing Stripe product ID.');
                }
                $cursor = $id;
                $products[$id] = $product;

                // https://docs.stripe.com/api/prices/list?lang=curl
                $prices = [];
                $priceCursor = null;
                do {
                    $remote = Remote::get('https://api.stripe.com/v1/prices', [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer '.strval($this->option('secret_key')),
                        ],
                        'data' => array_filter([
                            'product' => $id,
                            'active' => 'true',
                            'limit' => 100,
                            'currency' => strtolower($this->kart->currency()),
                            'starting_after' => $priceCursor,
                        ]),
                    ]);
                    $productPrices = $this->productResponseJson($remote, "Stripe prices for product $id");
                    $priceItems = A::get($productPrices, 'data');
                    if (! is_array($priceItems)) {
                        throw new RuntimeException("Malformed Stripe prices list for product $id.");
                    }
                    $previousPriceCursor = $priceCursor;
                    foreach ($priceItems as $price) {
                        if (! is_array($price)) {
                            throw new RuntimeException("Malformed Stripe price list item for product $id.");
                        }
                        $priceId = A::get($price, 'id');
                        if (! is_string($priceId) || trim($priceId) === '') {
                            throw new RuntimeException("Missing Stripe price ID for product $id.");
                        }
                        $priceCursor = $priceId;
                        $prices[] = $price;
                    }
                    $hasMorePrices = A::get($productPrices, 'has_more');
                    if (! is_bool($hasMorePrices)) {
                        throw new RuntimeException("Malformed Stripe price pagination for product $id.");
                    }
                    if ($hasMorePrices && ($priceCursor === null || $priceCursor === $previousPriceCursor)) {
                        throw new RuntimeException("Stripe price pagination cannot advance for product $id.");
                    }
                } while ($hasMorePrices);
                $products[$id]['prices'] = $prices;
            }

            $hasMore = A::get($json, 'has_more');
            if (! is_bool($hasMore)) {
                throw new RuntimeException('Malformed Stripe product pagination.');
            }
            if (! $hasMore) {
                break;
            }
            if ($cursor === null || $cursor === $previousCursor) {
                throw new RuntimeException('Stripe product pagination cannot advance.');
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
                        'featured' => fn ($i) => boolval(filter_var(A::get($i, 'metadata.featured'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false),
                        'variants' => function ($i) {
                            $variants = [];
                            foreach (A::get($i, 'prices', []) as $price) {
                                $v = A::get($price, 'metadata.variant', '');
                                if (! $v || empty(trim($v))) {
                                    continue;
                                }

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

        $remote = Remote::post('https://api.stripe.com/v1/billing_portal/sessions', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
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
