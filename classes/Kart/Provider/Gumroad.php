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

class Gumroad extends Provider
{
    protected string $name = ProviderEnum::GUMROAD->value;

    public function checkout(): string
    {
        // NOTE: webhook-only integration; Kart expects Gumroad sale webhook/licensing to finalize, no redirect is initiated here.
        return parent::checkout() ?? '/';
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }

    public function handleWebhook(array $payload, array $headers = []): WebhookResult
    {
        // https://rollout.com/integration-guides/gumroad/quick-guide-to-implementing-webhooks-in-gumroad
        $raw = strval(A::get($headers, '@raw_body', A::get($payload, '_raw', '')));
        $secret = strval($this->option('webhook_secret', true) ?? '');
        $signature = trim(strval(A::get($headers, 'x-gumroad-signature', '')));

        if ($secret === '' || $raw === '' || $signature === '') {
            return WebhookResult::invalid('missing webhook secret, raw body, or signature');
        }

        $expected = hash_hmac('sha256', $raw, $secret);
        if (! hash_equals($expected, $signature)) {
            return WebhookResult::invalid('invalid webhook signature');
        }

        $event = strtolower(strval(A::get($payload, 'event', 'sale')));
        $sale = A::get($payload, 'sale', $payload);

        $eventId = strval(A::get($sale, 'id', A::get($payload, 'id', '')));
        if ($eventId && $this->isDuplicateWebhook($eventId)) {
            return WebhookResult::ignored('duplicate webhook');
        }

        if ($event !== 'sale') {
            return WebhookResult::ignored('event not handled');
        }

        $paidAt = A::get($sale, 'purchased_at', A::get($payload, 'created_at'));
        $refunded = boolval(A::get($sale, 'refunded', false) || A::get($sale, 'disputed', false));
        if ($refunded) {
            return WebhookResult::ignored('refunded or disputed');
        }

        $paidAtTimestamp = $paidAt ? strtotime((string) $paidAt) : false;

        $data = array_filter([
            'email' => A::get($sale, 'email', A::get($sale, 'buyer_email')),
            'customer' => array_filter([
                'id' => A::get($sale, 'customer_id', A::get($sale, 'buyer_id')),
                'email' => A::get($sale, 'email', A::get($sale, 'buyer_email')),
                'name' => A::get($sale, 'full_name', A::get($sale, 'buyer_name')),
            ]),
            'paidDate' => $paidAtTimestamp !== false ? date('Y-m-d H:i:s', $paidAtTimestamp) : null,
            'paymentMethod' => A::get($sale, 'card', ''),
            'paymentComplete' => true,
            'invoiceurl' => A::get($sale, 'receipt_url', A::get($sale, 'short_url')),
            'paymentId' => $eventId,
        ], fn ($v) => $v !== null && $v !== [] && $v !== '');

        $uuid = kart()->option('products.product.uuid');
        if ($uuid instanceof \Closure === false) {
            return WebhookResult::invalid('missing product uuid resolver');
        }

        /** @var \Closure $likey */
        $likey = kart()->option('licenses.license.uuid');

        $quantity = max(1, intval(A::get($sale, 'quantity', 1)));
        $unitRaw = A::get($sale, 'price', A::get($sale, 'price_cents', 0));
        $unit = is_int($unitRaw) ? ($unitRaw / 100.0) :
            (is_string($unitRaw) && ctype_digit($unitRaw) ? (intval($unitRaw) / 100.0) : floatval($unitRaw));
        $total = $unit * $quantity;

        $data['items'][] = array_filter([
            'key' => ['page://'.$uuid(null, ['id' => A::get($sale, 'product_id', A::get($sale, 'product_permalink'))])], // pages field expects array
            'variant' => A::get($sale, 'variants', ''),
            'quantity' => $quantity,
            'price' => round($unit, 2),
            'total' => round($total, 2),
            'subtotal' => round($total, 2),
            'tax' => 0,
            'discount' => 0,
            'licensekey' => $likey($data + $sale + ['line' => $sale]),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);

        if ($eventId) {
            $this->rememberWebhook($eventId);
        }

        return WebhookResult::ok($data, 'Gumroad webhook processed');
    }

    public function fetchProducts(): array
    {
        $products = [];

        // https://gumroad.com/api#products
        $remote = Remote::get('https://api.gumroad.com/v2/products?access_token='.strval($this->option('access_token')), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        if (! is_array($json)) {
            return [];
        }

        foreach (A::get($json, 'products') as $product) {
            if (! A::get($product, 'published')) {
                continue;
            }
            if (A::get($product, 'deleted')) {
                continue;
            }
            $products[$product['id']] = $product;
        }

        return array_map(fn (array $data) =>
            // NOTE: changes here require a cache flush to take effect
        (new VirtualPage(
            $data,
            [
                // MAP: kirby <=> gumroad
                'id' => 'id', // id, uuid and slug will be hashed in ProductPage::create based on this `id`
                'title' => 'name',
                'content' => [
                    'created' => fn ($i) => date('Y-m-d H:i:s', time()),
                    'description' => 'description',
                    'price' => fn ($i) => A::get($i, 'price', 0) / 100.0,
                    'tags' => fn ($i) => implode(',', A::get($i, 'tags', [])),
                    // categories
                    'gallery' => fn ($i) => $this->findImagesFromUrls(
                        A::get($i, 'thumbnail_url', [])
                    ),
                    // downloads
                    // 'maxapo' => 'max_purchase_count',
                    // NOTE: has variants but without any metadata to map
                ],
            ],
            $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), $products);
    }
}
