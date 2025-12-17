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
use Kirby\Uuid\Uuid;

class Snipcart extends Provider
{
    protected string $name = ProviderEnum::SNIPCART->value;

    private function headers(): array
    {
        // https://docs.snipcart.com/v3/api-reference/authentication
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic '.base64_encode(strval($this->option('secret_key')).':'),
        ];
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }

    public function handleWebhook(array $payload, array $headers = []): WebhookResult
    {
        // https://docs.snipcart.com/v3/webhooks/introduction (token validation endpoint)
        $token = trim(strval(A::get($headers, 'x-snipcart-requesttoken', '')));
        if ($token === '') {
            return WebhookResult::invalid('missing webhook token');
        }

        $remote = Remote::get('https://app.snipcart.com/api/requestvalidation/'.$token, [
            'headers' => $this->headers(),
        ]);

        if ($remote->code() !== 200) {
            return WebhookResult::invalid('invalid webhook token');
        }

        $event = strtolower(strval(A::get($payload, 'eventName', '')));
        $content = A::get($payload, 'content', []);
        if (! is_array($content)) {
            return WebhookResult::invalid('missing webhook content');
        }

        $eventToken = strval(A::get($content, 'token', A::get($payload, 'token', '')));
        $eventId = $eventToken !== '' ? $event.'|'.$eventToken : '';
        if ($eventId !== '' && $this->isDuplicateWebhook($eventId)) {
            return WebhookResult::ignored('duplicate webhook');
        }

        $paymentStatus = strtolower(strval(A::get($content, 'paymentStatus', A::get($content, 'status', ''))));

        // process only paid/processed or completed orders to avoid duplicate drafts
        if ($event !== 'order.completed' && ! in_array($paymentStatus, ['paid', 'processed'], true)) {
            return WebhookResult::ignored('payment not completed');
        }

        $data = array_filter([
            'email' => A::get($content, 'email', A::get($content, 'user.email')),
            'customer' => array_filter([
                'id' => A::get($content, 'user.id'),
                'email' => A::get($content, 'user.email'),
                'name' => A::get($content, 'user.billingAddress.fullName'),
            ]),
            'paidDate' => ($date = A::get($content, 'completionDate', A::get($payload, 'createdOn'))) && ($timestamp = strtotime((string) $date)) !== false
                ? date('Y-m-d H:i:s', $timestamp)
                : null,
            'paymentMethod' => A::get($content, 'paymentMethod'),
            'paymentComplete' => in_array($paymentStatus, ['paid', 'processed'], true),
            'invoiceurl' => A::get($content, 'invoiceNumber'),
            'paymentId' => A::get($content, 'token'),
        ], fn ($v) => $v !== null && $v !== [] && $v !== '');

        $uuid = kart()->option('products.product.uuid');
        if ($uuid instanceof \Closure === false) {
            return WebhookResult::invalid('missing product uuid resolver');
        }

        /** @var \Closure $likey */
        $likey = kart()->option('licenses.license.uuid');

        foreach (A::get($content, 'items', []) as $line) {
            $unitPrice = A::get($line, 'unitPrice', A::get($line, 'price', 0));
            $quantity = max(1, intval(A::get($line, 'quantity', 1)));
            $total = A::get($line, 'totalPrice', $unitPrice * $quantity);

            $data['items'][] = array_filter([
                'key' => ['page://'.$uuid(null, ['id' => A::get($line, 'id')])], // pages field expects array
                'variant' => A::get($line, 'metadata.variant', ''),
                'quantity' => $quantity,
                'price' => round(floatval($unitPrice), 2),
                'total' => round(floatval($total), 2),
                'subtotal' => round(floatval($total), 2),
                'tax' => 0,
                'discount' => 0,
                'licensekey' => $likey($data + $line + ['line' => $line]),
            ], fn ($v) => $v !== null && $v !== '' && $v !== []);
        }

        if ($eventId !== '') {
            $this->rememberWebhook($eventId);
        }

        return WebhookResult::ok($data, 'Snipcart webhook processed');
    }

    public function fetchProducts(): array
    {
        $products = [];
        $offset = 0;
        $limit = 50;

        while (true) {
            // https://docs.snipcart.com/v3/api-reference/products
            $remote = Remote::get("https://app.snipcart.com/api/products?limit=$limit&offset=$offset", [
                'headers' => $this->headers(),
            ]);

            $json = $remote->code() === 200 ? $remote->json() : null;
            if (! is_array($json)) {
                break;
            }

            foreach (A::get($json, 'items') as $product) {
                // keep active products; skip archived ones
                if (A::get($product, 'archived')) {
                    continue;
                }
                $products[$product['id']] = $product;
            }

            if (count($products) >= intval(A::get($json, 'totalItems', 0))) {
                break;
            }
            $offset += $limit;
        }

        return array_map(fn (array $data) => // NOTE: changes here require a cache flush to take effect
        (new VirtualPage(
            $data,
            [
                // MAP: kirby <=> snipcart
                'id' => 'id', // id, uuid and slug will be hashed in ProductPage::create based on this `id`
                'title' => 'name',
                'content' => [
                    'created' => fn ($i) => date('Y-m-d H:i:s', strtotime($i['creationDate'])),
                    'description' => 'description',
                    'price' => fn ($i) => A::get($i, 'price', 0),
                    'tags' => fn ($i) => A::get($i, 'metadata.tags', A::get($i, 'metadata.tag', '')),
                    'categories' => fn ($i) => A::get($i, 'metadata.categories', A::get($i, 'metadata.category', '')),
                    'gallery' => fn ($i) => $this->findImagesFromUrls(
                        explode(',', A::get($i, 'image_url', A::get($i, 'custom_data.gallery', '')))
                    ),
                    'downloads' => fn ($i) => $this->findFilesFromUrls(
                        explode(',', A::get($i, 'metadata.downloads', ''))
                    ),
                ],
            ],
            $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), $products);
    }
}
