<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Provider\Gumroad;
use Kirby\Http\Remote;

uses()->group('providers');

it('fetches every Gumroad product page', function (): void {
    kart()->setOption('providers.gumroad.access_token', 'access-token');

    $gumroad = new class(kirby()) extends Gumroad
    {
        public array $requestedPageKeys = [];

        protected function requestProducts(?string $pageKey): Remote
        {
            $this->requestedPageKeys[] = $pageKey;
            $id = $pageKey === null ? 'first-product' : 'second-product';

            $remote = new Remote('https://api.gumroad.com/v2/products', ['test' => true]);
            $remote->info['http_code'] = 200;
            $remote->content = json_encode([
                'success' => true,
                'products' => [[
                    'id' => $id,
                    'name' => $id,
                    'description' => $id,
                    'price' => 1000,
                    'published' => true,
                    'deleted' => false,
                    'tags' => [],
                ]],
                'next_page_key' => $pageKey === null ? 'next-page' : null,
            ]);

            return $remote;
        }
    };

    $products = $gumroad->fetchProducts();

    expect($gumroad->requestedPageKeys)->toBe([null, 'next-page'])
        ->and(array_column($products, 'id'))->toBe(['first-product', 'second-product']);
});
