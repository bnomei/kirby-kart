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

        // https://polar.sh/docs/api-reference/operations/checkouts_create
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

        return parent::checkout() && $remote->code() < 300 ?
            A::get($json, 'url', '/') : '/';
    }

    public function completed(array $data = []): array
    {
        $checkoutId = get('checkout_id');
        if (! $checkoutId || ! is_string($checkoutId) || $checkoutId !== $this->kirby->session()->get('bnomei.kart.'.$this->name.'.session_id')) {
            return [];
        }

        $formatAmount = function (int|string|null $value): float {
            if (is_string($value)) {
                return str_contains($value, '.') ? round(floatval($value), 2) : round(intval($value) / 100.0, 2);
            }

            return round(intval($value) / 100.0, 2);
        };

        // https://polar.sh/docs/api-reference/operations/checkouts_get
        $remote = Remote::get($this->endpoint().'/checkouts/'.$checkoutId, [
            'headers' => $this->headers(),
        ]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        if (! is_array($json)) {
            return [];
        }

        $checkout = A::get($json, 'checkout', $json);
        if (! is_array($checkout)) {
            return [];
        }

        $status = strtolower(strval(A::get($checkout, 'status', '')));
        if (! in_array($status, ['completed', 'paid', 'succeeded', 'confirmed', 'ready', 'closed'], true)) {
            return [];
        }

        $paidAt = A::get($checkout, 'updated_at', A::get($checkout, 'created_at', time()));
        $data = array_merge($data, array_filter([
            'email' => A::get($checkout, 'customer_email'),
            'customer' => [
                'id' => A::get($checkout, 'customer_id'),
                'email' => A::get($checkout, 'customer_email'),
                'name' => A::get($checkout, 'customer_name'),
            ],
            'paidDate' => date('Y-m-d H:i:s', is_numeric($paidAt) ? intval($paidAt) : strtotime($paidAt ?: 'now')),
            'paymentComplete' => in_array($status, ['completed', 'paid', 'succeeded'], true),
            'invoiceurl' => A::get($checkout, 'invoice_id'),
            'paymentId' => A::get($checkout, 'id'),
        ]));

        // https://polar.sh/docs/api-reference/operations/orders_list
        $remote = Remote::get($this->endpoint().'/orders', [
            'headers' => $this->headers(),
            'data' => [
                'checkoutId' => $checkoutId,
                'limit' => 1,
            ],
        ]);

        $orderResponse = $remote->code() === 200 ? $remote->json() : null;
        $order = null;
        if (is_array($orderResponse)) {
            $order = A::get($orderResponse, 'orders.0', A::get($orderResponse, 'items.0'));
        }

        if (is_array($order)) {
            $orderPaid = boolval(A::get($order, 'paid', false) || in_array(strtolower(strval(A::get($order, 'status', ''))), ['completed', 'paid', 'succeeded', 'fulfilled'], true));
            $data['paymentComplete'] = $data['paymentComplete'] || $orderPaid;
            $data['invoiceurl'] = $data['invoiceurl'] ?? A::get($order, 'invoice_number', A::get($order, 'invoice_id'));
            $data['paymentId'] = $data['paymentId'] ?? A::get($order, 'id');
            $created = A::get($order, 'created_at', A::get($order, 'created'));
            if ($created) {
                $data['paidDate'] = date('Y-m-d H:i:s', is_numeric($created) ? intval($created) : strtotime($created));
            }

            $invoiceUrl = null;
            $orderId = A::get($order, 'id');
            if (is_string($orderId) && $orderId !== '') {
                // https://docs.polar.sh/api-reference/orders/get-invoice
                $remote = Remote::post($this->endpoint().'/orders/'.$orderId.'/invoice', [
                    'headers' => $this->headers(),
                ]);
                $invoiceJson = $remote->code() === 200 ? $remote->json() : null;
                if (is_array($invoiceJson)) {
                    $invoiceUrl = A::get($invoiceJson, 'url');
                }
            }
            if ($invoiceUrl) {
                $data['invoiceurl'] = $invoiceUrl;
            }

            $uuid = kart()->option('products.product.uuid');
            if ($uuid instanceof Closure === false) {
                return [];
            }

            /** @var \Closure $likey */
            $likey = kart()->option('licenses.license.uuid');

            foreach (A::get($order, 'items', []) as $line) {
                $productId = A::get($line, 'product_id', A::get($line, 'id'));
                if (! $productId) {
                    continue;
                }

                $amount = $formatAmount(A::get($line, 'amount', 0));
                $tax = $formatAmount(A::get($line, 'tax_amount', 0));
                $discount = $formatAmount(A::get($line, 'discount_amount', 0));

                $data['items'][] = [
                    'key' => ['page://'.$uuid(null, ['id' => $productId])],  // pages field expect an array
                    'variant' => A::get($line, 'description', A::get($line, 'name', '')),
                    'quantity' => intval(A::get($line, 'quantity', 1)),
                    'price' => $formatAmount(A::get($line, 'unit_price', A::get($line, 'unit_amount', $amount))),
                    // these values include the multiplication with quantity
                    'total' => $amount,
                    'subtotal' => max(0, $amount - $tax - $discount),
                    'tax' => $tax,
                    'discount' => $discount,
                    'licensekey' => $likey($data + ['line' => $line, 'order' => $order]),
                ];
            }
        }

        if (A::get($data, 'paymentComplete') !== true) {
            $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.session_id');

            return [];
        }

        $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.session_id');

        return parent::completed($data);
    }

    public function fetchProducts(): array
    {
        $products = [];
        $page = 1;

        while (true) {
            // https://polar.sh/docs/api-reference/operations/products_list
            $remote = Remote::get($this->endpoint().'/products', [
                'headers' => $this->headers(),
                'data' => [
                    'page' => $page,
                    'limit' => 100,
                    'is_archived' => 'false',
                    'is_recurring' => 'false', // one-time purchases only
                ],
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

        return array_map(fn (array $data) => (new VirtualPage(
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
