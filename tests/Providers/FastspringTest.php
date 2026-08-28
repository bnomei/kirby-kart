<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Provider\Fastspring;
use Kirby\Http\Remote;

uses()->group('providers');

it('fetches every FastSpring product page', function (): void {
    kart()->setOption('providers.fastspring.username', 'username');
    kart()->setOption('providers.fastspring.password', 'password');

    $fastspring = new class(kirby()) extends Fastspring
    {
        public array $requestedPages = [];

        public array $requestedProducts = [];

        protected function requestProducts(?int $page): Remote
        {
            $this->requestedPages[] = $page;

            return $this->response([
                'products' => [$page === null ? 'first-product' : 'second-product'],
                'nextPage' => $page === null ? 2 : null,
            ]);
        }

        protected function requestProduct(string $path): Remote
        {
            $this->requestedProducts[] = $path;

            return $this->response([
                'products' => [[
                    'product' => $path,
                    'display' => ['en' => $path],
                    'description' => ['summary' => ['en' => $path]],
                    'pricing' => ['price' => ['EUR' => 10]],
                ]],
            ]);
        }

        private function response(array $payload): Remote
        {
            $remote = new Remote('https://api.fastspring.com/products', ['test' => true]);
            $remote->info['http_code'] = 200;
            $remote->content = json_encode($payload);

            return $remote;
        }
    };

    $products = $fastspring->fetchProducts();

    expect($fastspring->requestedPages)->toBe([null, 2])
        ->and($fastspring->requestedProducts)->toBe(['first-product', 'second-product'])
        ->and(array_column($products, 'id'))->toBe(['first-product', 'second-product']);
});
