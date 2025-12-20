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
use Bnomei\Kart\WebhookResult;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

class Shopify extends Provider
{
    protected string $name = ProviderEnum::SHOPIFY->value;

    public function checkout(): string
    {
        $lines = [];
        foreach ($this->kart->cart()->lines() as $line) {
            $product = $line->product();
            $variantData = $product?->variantDataForVariant($line->variant(), resolveImage: false);
            if (! $variantData && $product) {
                $variants = $product->variantData(false);
                $variantData = reset($variants) ?: null;
            }

            $variantId = $variantData['price_id'] ?? null;
            if (! $variantId) {
                continue;
            }

            $lines[] = [
                'merchandiseId' => $this->variantGid(strval($variantId)),
                'quantity' => max(1, $line->quantity()),
            ];
        }

        if (empty($lines)) {
            throw new \RuntimeException('No Shopify cart lines to process');
        }

        $contact = $this->checkoutContact();
        $name = $this->checkoutNameParts();
        $shippingAddress = $this->checkoutShippingAddress();

        $buyerIdentity = array_filter([
            'email' => $contact['email'] ?? $this->kirby->user()?->email(),
            'phone' => $contact['phone'] ?? null,
            'firstName' => $name['first'] ?? null,
            'lastName' => $name['last'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if (! empty($shippingAddress)) {
            $buyerIdentity['deliveryAddressPreferences'] = [[
                'deliveryAddress' => array_filter([
                    'address1' => $shippingAddress['address1'] ?? null,
                    'address2' => $shippingAddress['address2'] ?? null,
                    'city' => $shippingAddress['city'] ?? null,
                    'province' => $shippingAddress['state'] ?? null,
                    'zip' => $shippingAddress['postal_code'] ?? null,
                    'country' => isset($shippingAddress['country']) ? strtoupper($shippingAddress['country']) : null,
                    'firstName' => $shippingAddress['first_name'] ?? ($name['first'] ?? null),
                    'lastName' => $shippingAddress['last_name'] ?? ($name['last'] ?? null),
                    'phone' => $shippingAddress['phone'] ?? ($contact['phone'] ?? null),
                    'company' => $shippingAddress['company'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''),
            ]];
        }

        $input = array_filter([
            'lines' => $lines,
            'buyerIdentity' => empty($buyerIdentity) ? null : $buyerIdentity,
        ]);

        $remote = Remote::post($this->storefrontEndpoint(), [
            'headers' => $this->storefrontHeaders(),
            'data' => json_encode([
                'query' => <<<'GQL'
mutation cartCreate($input: CartInput!) {
  cartCreate(input: $input) {
    cart {
      id
      checkoutUrl
    }
    userErrors {
      field
      message
    }
  }
}
GQL,
                'variables' => [
                    'input' => $input,
                ],
            ]),
        ]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        $errors = is_array($json) ? A::get($json, 'errors', []) : [];
        $userErrors = is_array($json) ? A::get($json, 'data.cartCreate.userErrors', []) : [];

        if (! empty($errors)) {
            throw new \RuntimeException('Shopify cartCreate failed: '.json_encode($errors));
        }
        if (! empty($userErrors)) {
            throw new \RuntimeException('Shopify cartCreate user errors: '.json_encode($userErrors));
        }

        $checkoutUrl = is_array($json) ? A::get($json, 'data.cartCreate.cart.checkoutUrl') : null;
        if (! $checkoutUrl) {
            throw new \RuntimeException('Shopify cartCreate returned no checkoutUrl');
        }

        $channel = strval($this->option('checkout_channel') ?? 'headless-storefront');
        if ($channel !== '') {
            $checkoutUrl .= (str_contains($checkoutUrl, '?') ? '&' : '?').'channel='.$channel;
        }

        return parent::checkout() ? $checkoutUrl : '/';
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }

    public function handleWebhook(array $payload, array $headers = []): WebhookResult
    {
        $raw = strval(A::get($headers, '@raw_body', A::get($payload, '_raw', '')));
        $hmac = trim(strval(A::get($headers, 'x-shopify-hmac-sha256', '')));
        $secret = strval($this->option('webhook_secret'));

        if ($secret === '' || $raw === '' || $hmac === '') {
            return WebhookResult::invalid('missing webhook secret or signature');
        }

        $digest = base64_encode(hash_hmac('sha256', $raw, $secret, true));
        if (! hash_equals($digest, $hmac)) {
            return WebhookResult::invalid('invalid signature');
        }

        $eventId = strval(A::get($headers, 'x-shopify-event-id', A::get($headers, 'x-shopify-webhook-id', '')));
        if ($eventId && $this->isDuplicateWebhook($eventId)) {
            return WebhookResult::ignored('duplicate webhook');
        }
        if ($eventId) {
            $this->rememberWebhook($eventId);
        }

        $topic = strval(A::get($headers, 'x-shopify-topic', ''));
        if ($topic !== 'orders/paid') {
            return WebhookResult::ignored('topic not handled');
        }

        $orderId = strval(A::get($payload, 'id', ''));
        if (! $orderId) {
            return WebhookResult::invalid('missing order id');
        }

        $uuid = kart()->option('products.product.uuid');
        $likey = kart()->option('licenses.license.uuid');

        $items = [];
        foreach (A::get($payload, 'line_items', []) as $line) {
            $quantity = max(1, intval(A::get($line, 'quantity', 1)));
            $unitPrice = round(floatval(A::get($line, 'price_set.shop_money.amount', A::get($line, 'price', 0))), 2);
            $linePrice = round($unitPrice * $quantity, 2);
            $discount = round(floatval(A::get($line, 'total_discount_set.shop_money.amount', 0)), 2);

            $key = null;
            if ($uuid instanceof \Closure) {
                $key = 'page://'.$uuid(null, ['id' => strval(A::get($line, 'product_id'))]);
            }

            $items[] = array_filter([
                'key' => $key ? [$key] : null,
                'variant' => strval(A::get($line, 'variant_title', '')),
                'quantity' => $quantity,
                'price' => $unitPrice,
                'subtotal' => $linePrice,
                'discount' => $discount,
                'total' => max(0, $linePrice - $discount),
                'licensekey' => $likey instanceof \Closure ? $likey(['order' => $payload, 'line' => $line]) : null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        $orderData = array_filter([
            'order_id' => $orderId,
            'paymentId' => 'shopify:'.$orderId,
            'email' => A::get($payload, 'email'),
            'paymentComplete' => A::get($payload, 'financial_status') === 'paid',
            'paidDate' => ($processed = A::get($payload, 'processed_at')) && ($processedTimestamp = strtotime((string) $processed)) !== false
                ? date('Y-m-d H:i:s', $processedTimestamp)
                : null,
            'shop' => A::get($headers, 'x-shopify-shop-domain'),
            'topic' => $topic,
            'items' => $items,
            'raw' => $payload,
        ], fn ($v) => $v !== null && $v !== []);

        return WebhookResult::ok($orderData, 'Shopify webhook processed');
    }

    public function fetchProducts(): array
    {
        $products = [];
        $pageInfo = null;

        // REST Admin products listing
        while (true) {
            // https://shopify.dev/docs/api/admin-rest/2024-07/resources/product#get-products
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
            $headers = $remote->headers();
            $link = A::get($headers, 'Link', A::get($headers, 'link'));
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
        $version = strval($this->option('api_version') ?? '2025-01');

        return 'https://'.$domain.'/api/'.$version.'/graphql.json';
    }

    private function storefrontHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-Shopify-Storefront-Access-Token' => strval($this->option('storefront_token')),
        ];
    }

    private function variantGid(string $variantId): string
    {
        return str_contains($variantId, 'gid://') ? $variantId : 'gid://shopify/ProductVariant/'.$variantId;
    }
}
