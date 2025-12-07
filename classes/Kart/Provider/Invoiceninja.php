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
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

class Invoiceninja extends Provider
{
    protected string $name = ProviderEnum::INVOICE_NINJA->value;

    public function checkout(): string
    {
        // NOTE: webhook-only integration; Kart expects Invoice Ninja webhooks for payment confirmation and does not initiate hosted links here.
        return parent::checkout();
    }

    public function completed(array $data = []): array
    {
        // Invoice Ninja requires webhooks to confirm payments; nothing to poll here.
        $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.session_id');

        return parent::completed($data);
    }

    public function fetchProducts(): array
    {
        $products = [];
        $page = 1;

        while (true) {
            $remote = Remote::get($this->endpoint().'/products', [
                'headers' => $this->headers(),
                'data' => [
                    'per_page' => 200,
                    'page' => $page,
                ],
            ]);

            $json = $remote->code() === 200 ? $remote->json() : null;
            if (! is_array($json)) {
                break;
            }

            foreach (A::get($json, 'data', []) as $product) {
                $products[A::get($product, 'id')] = $product;
            }

            $total = intval(A::get($json, 'meta.pagination.total_pages', 1));
            if ($page >= $total) {
                break;
            }
            $page++;
        }

        return array_map(fn (array $data) => (new VirtualPage(
            $data,
            [
                'id' => 'id',
                'title' => fn ($i) => A::get($i, 'product_key', A::get($i, 'id')),
                'content' => [
                    'description' => 'notes',
                    'price' => fn ($i) => floatval(A::get($i, 'price', A::get($i, 'cost', 0))),
                    'tags' => fn ($i) => A::get($i, 'custom_value1', ''),
                    'categories' => fn ($i) => A::get($i, 'custom_value2', ''),
                ],
            ],
            $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), $products);
    }

    private function endpoint(): string
    {
        $endpoint = strval($this->option('endpoint'));

        return rtrim($endpoint ?: 'https://app.invoicing.co/api/v1', '/');
    }

    private function headers(bool $json = false): array
    {
        $headers = [
            'Authorization' => 'Bearer '.strval($this->option('token')),
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];

        if ($json) {
            $headers['Content-Type'] = 'application/json';
        }

        if ($company = $this->option('company_key')) {
            $headers['X-Company-Token'] = $company;
        }

        return $headers;
    }
}
