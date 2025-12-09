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
use Bnomei\Kart\Router;
use Bnomei\Kart\VirtualPage;
use Bnomei\Kart\WebhookResult;
use Closure;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

class Lemonsqueezy extends Provider
{
    protected string $name = ProviderEnum::LEMONSQUEEZY->value;

    /* Checkout but no redirect
    public function checkout(): string
    {
        $product = $this->kart->cart()->lines()->first()?->product();

        return parent::checkout() && $product ?
            A::get($product->raw()->yaml(), 'buy_now_url') : '/';
    }
    */

    public function checkout(): string
    {
        $line = $this->kart->cart()->lines()->first();
        $product = $line->product();

        $options = $this->option('checkout_options', false);
        if ($options instanceof Closure) {
            $options = $options($this->kart);
        }

        $variantId = A::get($product?->raw()->yaml(), 'variants.0.id');
        if ($product && $line->variant()) {
            $variantId = $product->priceIdForVariant($line->variant());
        }

        // https://docs.lemonsqueezy.com/api/checkouts/create-checkout
        $remote = Remote::post('https://api.lemonsqueezy.com/v1/checkouts', [
            'headers' => [
                'Content-Type' => 'application/vnd.api+json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
            'data' => json_encode([
                'data' => [
                    'type' => 'checkouts',
                    'relationships' => [
                        'store' => [
                            'data' => [
                                'type' => 'stores',
                                'id' => $this->option('store_id'),
                            ],
                        ],
                        'variant' => [
                            'data' => [
                                'type' => 'variants',
                                'id' => $variantId,
                            ],
                        ],
                    ],
                    'attributes' => array_filter(array_merge([
                        'product_options' => [
                            'enabled_variants' => [$variantId], // NOTE: array
                            'redirect_url' => url(Router::PROVIDER_SUCCESS).'?order_id=[order_id]',
                        ],
                        'checkout_data' => array_filter([
                            'email' => $this->kirby->user()?->email(),
                            'name' => $this->kirby->user()?->name()->value(),
                        ]),
                        'test_mode' => $this->kirby->environment()->isLocal(),
                        'expires_at' => date('c', time() + 60 * 60), // 1h
                    ], $options)),
                ],
            ]),
        ]);

        $json = in_array($remote->code(), [200, 201]) ? $remote->json() : null;
        if (! is_array($json)) {
            throw new \Exception('Checkout failed', $remote->code());
        }

        $session_id = A::get($json, 'data.id');
        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.session_id', $session_id);

        return parent::checkout() && in_array($remote->code(), [200, 201]) ?
            A::get($json, 'data.attributes.url') : '/';
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }

    public function handleWebhook(array $payload, array $headers = []): WebhookResult
    {
        // https://docs.lemonsqueezy.com/help/webhooks/signing-requests
        $headers = array_change_key_case($headers, CASE_LOWER);
        $raw = strval(A::get($headers, '@raw_body', A::get($payload, '_raw', '')));
        $signature = trim(strval(A::get($headers, 'x-signature', '')));
        $secret = strval($this->option('webhook_secret', true) ?? '');

        if ($secret === '' || $raw === '' || $signature === '') {
            return WebhookResult::invalid('missing webhook secret, raw body, or signature');
        }

        if (! hash_equals(hash_hmac('sha256', $raw, $secret), $signature)) {
            return WebhookResult::invalid('invalid webhook signature');
        }

        $event = strtolower(strval(A::get($payload, 'meta.event_name', A::get($headers, 'x-event-name', ''))));

        $allowed = $this->option('webhook_events');
        if (is_array($allowed) && $event && ! in_array($event, array_map(
            static fn ($value) => strtolower(strval($value)),
            $allowed
        ), true)) {
            return WebhookResult::ignored('event not handled');
        }

        $data = A::get($payload, 'data', $payload);
        if (! is_array($data)) {
            return WebhookResult::invalid('missing webhook payload data');
        }

        $eventId = strval(
            A::get($headers, 'x-event-id') ??
            A::get($payload, 'meta.event_id') ??
            A::get($data, 'id')
        );
        if ($eventId !== '' && $this->isDuplicateWebhook($eventId)) {
            return WebhookResult::ignored('duplicate webhook');
        }

        $type = strtolower(strval(A::get($data, 'type', A::get($payload, 'type', ''))));
        $orderData = [];

        if ($type === 'orders') {
            $orderData = $this->mapOrderData($data);
        } elseif (in_array($type, ['subscription-invoices', 'subscription_invoices'], true)) {
            $subscription = null;
            $subscriptionId = strval(A::get($data, 'attributes.subscription_id', A::get($payload, 'subscription_id', '')));
            if ($subscriptionId !== '') {
                $subscription = $this->fetchSubscription($subscriptionId);
            }
            $orderData = $this->mapSubscriptionInvoice($data, $subscription);
        } elseif ($orderId = strval(A::get($payload, 'order_id', ''))) {
            $order = $this->fetchOrder($orderId);
            if ($order) {
                $orderData = $this->mapOrderData($order);
            }
        }

        if (empty($orderData)) {
            return WebhookResult::invalid('unable to map webhook payload');
        }

        if ($eventId !== '') {
            $this->rememberWebhook($eventId);
        }

        return WebhookResult::ok($orderData, 'Lemon Squeezy webhook processed');
    }

    public function fetchProducts(): array
    {
        $products = [];
        $page = 1;

        while (true) {
            // https://docs.lemonsqueezy.com/api/products/list-all-products
            $remote = Remote::get('https://api.lemonsqueezy.com/v1/products', [
                'headers' => [
                    'Accept' => 'application/vnd.api+json',
                    'Content-Type' => 'application/vnd.api+json',
                    'Authorization' => 'Bearer '.strval($this->option('secret_key')),
                ],
                'data' => array_filter([
                    'filter[store_id]' => $this->option('store_id'),
                    'page[number]' => $page,
                ]),
            ]);

            $json = $remote->code() === 200 ? $remote->json() : null;
            if (! is_array($json)) {
                break;
            }

            foreach (A::get($json, 'data') as $product) {
                if (A::get($product, 'attributes.status') !== 'published') {
                    continue;
                }
                $variants = [];
                // https://docs.lemonsqueezy.com/api/variants/list-all-variants
                $remote = Remote::get('https://api.lemonsqueezy.com/v1/variants', [
                    'headers' => [
                        'Accept' => 'application/vnd.api+json',
                        'Content-Type' => 'application/vnd.api+json',
                        'Authorization' => 'Bearer '.strval($this->option('secret_key')),
                    ],
                    'data' => array_filter([
                        'filter[product_id]' => $product['id'],
                    ]),
                ]);

                $productVariants = $remote->code() === 200 ? $remote->json() : null;
                if (is_array($productVariants)) {
                    foreach (A::get($productVariants, 'data', []) as $variant) {
                        // NOTE: the default variant will always be pending status
                        if (strtolower(A::get($variant, 'name', 'default')) !== 'default' &&
                        A::get($variant, 'attributes.status') !== 'published') {
                            continue;
                        }
                        $variants[] = $variant['attributes'] + ['id' => $variant['id']];
                    }
                }

                $products[$product['id']] = $product['attributes'] + [
                    'id' => $product['id'],
                    'variants' => $variants,
                ];
            }

            if (A::get($json, 'meta.page.lastPage') <= $page) {
                break;
            }
            $page++;
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
                    'description' => fn ($i) => Str::unhtml(A::get($i, 'description', '')),
                    'price' => fn ($i) => A::get($i, 'price', 0) / 100.0,
                    // 'tags' => fn ($i) => A::get($i, 'metadata.tags', []),
                    // 'categories' => fn ($i) => A::get($i, 'metadata.categories', []),
                    'gallery' => fn ($i) => $this->findImagesFromUrls(
                        A::get($i, 'large_thumb_url', A::get($i, 'thumb_url', []))
                    ),
                    // 'downloads' => fn ($i) => $this->findFilesFromUrls(
                    //     A::get($i, 'metadata.downloads', [])
                    // ),
                    'variants' => function ($i) {
                        $variants = [];
                        foreach (A::get($i, 'variants', []) as $variant) {
                            $variants[] = [
                                'price_id' => $variant['id'],
                                'variant' => 'variant:'.A::get($variant, 'name', 'default'),
                                'price' => round(A::get($variant, 'price', 0) / 100.0, 2),
                                // 'image' => explode(',', A::get($variant, 'metadata.image', '')),
                            ];
                        }

                        return empty($variants) ? null : $variants;
                    },
                ],
            ],
            $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), $products);
    }

    public function portal(?string $returnUrl = null): string
    {
        // subscriptions
        // 'https://'.$this->option('store_id').'.lemonsqueezy.com/billing';

        // one-time orders
        return 'https://app.lemonsqueezy.com/my-orders';
    }

    private function mapOrderData(array $order): array
    {
        $attributes = A::get($order, 'attributes', $order);

        $data = array_filter([
            'email' => A::get($attributes, 'user_email'),
            'customer' => array_filter([
                'id' => A::get($attributes, 'customer_id'),
                'email' => A::get($attributes, 'user_email'),
                'name' => A::get($attributes, 'user_name'),
            ]),
            'paidDate' => $this->normalizeDate(A::get($attributes, 'created_at')),
            'paymentComplete' => A::get($attributes, 'status') === 'paid' && ! A::get($attributes, 'refunded', false),
            'invoiceurl' => A::get($attributes, 'urls.receipt'),
            'paymentId' => A::get($order, 'id'),
        ]);

        $uuid = kart()->option('products.product.uuid');
        if ($uuid instanceof Closure === false) {
            return [];
        }

        $likey = kart()->option('licenses.license.uuid');

        $firstItem = A::get($attributes, 'first_order_item', []);
        $productId = A::get($firstItem, 'product_id');

        if ($productId) {
            $price = round(A::get($firstItem, 'price', 0) / 100.0, 2);
            $total = round(A::get($attributes, 'total', A::get($firstItem, 'price', 0)) / 100.0, 2);
            $data['items'][] = array_filter([
                'key' => ['page://'.$uuid(null, ['id' => $productId])],
                'variant' => 'variant:'.A::get($firstItem, 'variant_name', 'default'),
                'quantity' => 1,
                'price' => $price,
                'total' => $total,
                'subtotal' => round(A::get($attributes, 'subtotal', $total) / 100.0, 2),
                'tax' => round(A::get($attributes, 'tax', 0) / 100.0, 2),
                'discount' => round(A::get($attributes, 'discount_total', 0) / 100.0, 2),
                'licensekey' => $likey instanceof Closure ? $likey($data + $attributes + $order) : null,
            ], fn ($v) => $v !== null && $v !== '' && $v !== []);
        }

        return $data;
    }

    private function mapSubscriptionInvoice(array $invoice, ?array $subscription = null): array
    {
        $attributes = A::get($invoice, 'attributes', $invoice);
        $subscriptionAttributes = $subscription ? A::get($subscription, 'attributes', $subscription) : [];

        $data = array_filter([
            'email' => A::get($attributes, 'user_email'),
            'customer' => array_filter([
                'id' => A::get($attributes, 'customer_id'),
                'email' => A::get($attributes, 'user_email'),
                'name' => A::get($attributes, 'user_name'),
            ]),
            'paidDate' => $this->normalizeDate(A::get($attributes, 'created_at')),
            'paymentComplete' => A::get($attributes, 'status') === 'paid' && ! A::get($attributes, 'refunded', false),
            'invoiceurl' => A::get($attributes, 'urls.invoice_url'),
            'paymentId' => A::get($invoice, 'id'),
        ]);

        $uuid = kart()->option('products.product.uuid');
        if ($uuid instanceof Closure === false) {
            return [];
        }

        $likey = kart()->option('licenses.license.uuid');

        $productId = A::get($subscriptionAttributes, 'product_id');
        if ($productId) {
            $quantity = max(1, intval(A::get($subscriptionAttributes, 'first_subscription_item.quantity', 1)));
            $total = round(A::get($attributes, 'total', 0) / 100.0, 2);
            $subtotal = round(A::get($attributes, 'subtotal', $total) / 100.0, 2);
            $price = round($subtotal / max(1, $quantity), 2);
            $data['items'][] = array_filter([
                'key' => ['page://'.$uuid(null, ['id' => $productId])],
                'variant' => 'variant:'.A::get($subscriptionAttributes, 'variant_name', 'default'),
                'quantity' => $quantity,
                'price' => $price,
                'total' => $total,
                'subtotal' => $subtotal,
                'tax' => round(A::get($attributes, 'tax', 0) / 100.0, 2),
                'discount' => round(A::get($attributes, 'discount_total', 0) / 100.0, 2),
                'licensekey' => $likey instanceof Closure ? $likey($data + $attributes + ['subscription' => $subscriptionAttributes]) : null,
            ], fn ($v) => $v !== null && $v !== '' && $v !== []);
        }

        return $data;
    }

    private function normalizeDate(int|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = is_numeric($value) ? intval($value) : strtotime((string) $value);

        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function fetchOrder(string $orderId): ?array
    {
        $remote = Remote::get('https://api.lemonsqueezy.com/v1/orders/'.$orderId, [
            'headers' => [
                'Content-Type' => 'application/vnd.api+json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
        ]);

        $json = $remote->code() === 200 ? $remote->json() : null;

        return is_array($json) ? A::get($json, 'data') : null;
    }

    private function fetchSubscription(string $subscriptionId): ?array
    {
        $remote = Remote::get('https://api.lemonsqueezy.com/v1/subscriptions/'.$subscriptionId, [
            'headers' => [
                'Content-Type' => 'application/vnd.api+json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
        ]);

        $json = $remote->code() === 200 ? $remote->json() : null;

        return is_array($json) ? A::get($json, 'data') : null;
    }
}
