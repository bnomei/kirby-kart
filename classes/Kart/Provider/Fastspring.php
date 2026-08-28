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

class Fastspring extends Provider
{
    protected string $name = ProviderEnum::FASTSPRING->value;

    private function headers(): array
    {
        // https://developer.fastspring.com/reference/authentication
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic '.base64_encode(strval($this->option('username')).':'.strval($this->option('password'))),
        ];
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }

    public function handleWebhook(array $payload, array $headers = []): WebhookResult
    {
        // TODO: implement FastSpring webhook parsing, signature verification, idempotency check, and mapping to orderData.
        return WebhookResult::ignored('FastSpring webhook handling not implemented yet.');
    }

    public function fetchProducts(): array
    {
        if ($this->canSyncProducts() === false) {
            return [];
        }

        $products = [];
        $paths = [];
        $page = null;
        $seenPages = [];

        // https://developer.fastspring.com/reference/list-all-product-paths
        do {
            $json = $this->productResponseJson(
                $this->requestProducts($page),
                'FastSpring products'
            );
            $pagePaths = A::get($json, 'products');
            if (! is_array($pagePaths)) {
                throw new \RuntimeException('Malformed FastSpring products list.');
            }

            foreach ($pagePaths as $path) {
                if (! is_string($path) || trim($path) === '') {
                    throw new \RuntimeException('Malformed FastSpring product path.');
                }
                $paths[trim($path)] = true;
            }

            $nextPage = A::get($json, 'nextPage');
            if ($nextPage === null) {
                break;
            }
            if (! is_int($nextPage) && (! is_string($nextPage) || ! ctype_digit($nextPage))) {
                throw new \RuntimeException('Malformed FastSpring product pagination.');
            }

            $page = intval($nextPage);
            if (isset($seenPages[$page])) {
                throw new \RuntimeException('FastSpring product pagination cannot advance.');
            }
            $seenPages[$page] = true;
        } while (true);

        foreach (array_keys($paths) as $path) {
            // https://developer.fastspring.com/reference/retrieve-a-product
            $product = $this->productResponseJson(
                $this->requestProduct($path),
                'FastSpring product '.$path
            );
            $details = A::get($product, 'products');
            if (! is_array($details) || $details === []) {
                throw new \RuntimeException('Malformed FastSpring product detail.');
            }

            foreach ($details as $p) {
                if (! is_array($p)) {
                    throw new \RuntimeException('Malformed FastSpring product detail item.');
                }
                $id = trim(strval(A::get($p, 'product')));
                if ($id === '') {
                    throw new \RuntimeException('FastSpring product is missing an ID.');
                }
                $products[$id] = $p;
            }
        }

        $currency = kart()->currency();

        return array_map(fn (array $data) =>
            // NOTE: changes here require a cache flush to take effect
        (new VirtualPage(
            $data,
            [
                // MAP: kirby <=> fastspring
                'id' => 'product', // id, uuid and slug will be hashed in ProductPage::create based on this `id`
                'title' => 'display.en',
                'content' => [
                    'created' => fn ($i) => date('Y-m-d H:i:s', time()),
                    'description' => 'description.summary.en',
                    'price' => fn ($i) => A::get($i, 'pricing.price.'.$currency, 0),
                    // tags
                    // categories
                    'gallery' => fn ($i) => $this->findImagesFromUrls(
                        A::get($i, 'image', '')
                    ),
                    // downloads
                ],
            ],
            $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), $products);
    }

    protected function requestProducts(?int $page): Remote
    {
        $url = 'https://api.fastspring.com/products';
        if ($page !== null) {
            $url .= '?page='.$page;
        }

        return Remote::get($url, [
            'headers' => $this->headers(),
        ]);
    }

    protected function requestProduct(string $path): Remote
    {
        return Remote::get('https://api.fastspring.com/products/'.rawurlencode($path), [
            'headers' => $this->headers(),
        ]);
    }

    protected function canSyncProducts(): bool
    {
        return trim(strval($this->option('username'))) !== ''
            && trim(strval($this->option('password'))) !== '';
    }
}
