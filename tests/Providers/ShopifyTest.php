<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Provider\Shopify;
use Kirby\Http\Remote;

uses()->group('providers');

beforeEach(function (): void {
    kirby()->cache('bnomei.kart.shopify')->flush();
    kart()->setOption('providers.shopify.api_version', '');
});

afterEach(function (): void {
    kirby()->cache('bnomei.kart.shopify')->flush();
});

function shopifyAdminHeadersForTest(Shopify $shopify): array
{
    $method = new ReflectionMethod(Shopify::class, 'adminHeaders');

    return $method->invoke($shopify);
}

function shopifyEndpointForTest(Shopify $shopify, string $method): string
{
    $method = new ReflectionMethod(Shopify::class, $method);

    return $method->invoke($shopify);
}

it('uses the fallback Shopify API version by default', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.api_version', '');

    $shopify = new Shopify(kirby());

    expect(shopifyEndpointForTest($shopify, 'adminEndpoint'))->toBe('https://example.myshopify.com/admin/api/2024-07')
        ->and(shopifyEndpointForTest($shopify, 'storefrontEndpoint'))->toBe('https://example.myshopify.com/api/2024-07/graphql.json');
});

it('allows overriding the Shopify API version from config', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.api_version', '2026-01');

    $shopify = new Shopify(kirby());

    expect(shopifyEndpointForTest($shopify, 'adminEndpoint'))->toBe('https://example.myshopify.com/admin/api/2026-01')
        ->and(shopifyEndpointForTest($shopify, 'storefrontEndpoint'))->toBe('https://example.myshopify.com/api/2026-01/graphql.json');
});

it('requests a fresh Shopify admin token from client credentials for each admin request', function (): void {
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');

    $shopify = new class(kirby()) extends Shopify
    {
        public array $requests = [];

        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            $this->requests[] = [$clientId, $clientSecret];

            return 'fresh-token-'.count($this->requests);
        }
    };

    $first = shopifyAdminHeadersForTest($shopify);
    $second = shopifyAdminHeadersForTest($shopify);

    expect($first['X-Shopify-Access-Token'])->toBe('fresh-token-1')
        ->and($second['X-Shopify-Access-Token'])->toBe('fresh-token-2')
        ->and($shopify->requests)->toBe([
            ['client-id', 'client-secret'],
            ['client-id', 'client-secret'],
        ]);
});

it('requires Shopify client credentials for admin headers', function (): void {
    kart()->setOption('providers.shopify.client_id', '');
    kart()->setOption('providers.shopify.client_secret', '');

    $shopify = new class(kirby()) extends Shopify
    {
        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            return 'unexpected-token';
        }
    };

    expect(fn () => shopifyAdminHeadersForTest($shopify))
        ->toThrow(RuntimeException::class, 'Shopify client_id and client_secret are required to request an Admin API token');
});

it('skips Shopify product sync without a store domain', function (): void {
    kart()->setOption('providers.shopify.store_domain', '');
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');

    $shopify = new class(kirby()) extends Shopify
    {
        public bool $requested = false;

        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            $this->requested = true;

            return 'unexpected-token';
        }
    };

    expect($shopify->fetchProducts())->toBe([])
        ->and($shopify->requested)->toBeFalse();
});

it('skips Shopify product sync without client credentials', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.client_id', '');
    kart()->setOption('providers.shopify.client_secret', '');

    $shopify = new class(kirby()) extends Shopify
    {
        public bool $requested = false;

        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            $this->requested = true;

            return 'unexpected-token';
        }
    };

    expect($shopify->fetchProducts())->toBe([])
        ->and($shopify->requested)->toBeFalse();
});

it('throws a Shopify product sync error when the admin token request fails', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');
    kirby()->cache('bnomei.kart.shopify')->flush();

    $shopify = new class(kirby()) extends Shopify
    {
        public bool $requested = false;

        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            $this->requested = true;

            throw new RuntimeException('token failed');
        }
    };

    expect(fn () => $shopify->products())
        ->toThrow(RuntimeException::class, 'token failed')
        ->and($shopify->requested)->toBeTrue();
});

it('keeps cached Shopify products when a refresh fails', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');
    kirby()->cache('bnomei.kart.shopify')->flush();

    $shopify = new class(kirby()) extends Shopify
    {
        public bool $requested = false;

        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            $this->requested = true;

            throw new RuntimeException('token failed');
        }
    };

    $cached = [
        [
            'id' => 'cached-product',
        ],
    ];
    $shopify->cache()->set('products', $cached, 5);

    expect(fn () => $shopify->sync('products'))
        ->toThrow(RuntimeException::class, 'token failed');

    expect($shopify->products())->toBe($cached)
        ->and($shopify->requested)->toBeTrue();
});

it('throws Shopify products API errors', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');

    $shopify = new class(kirby()) extends Shopify
    {
        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            return 'fresh-token';
        }

        protected function requestAdminProducts(?string $pageInfo): Remote
        {
            $remote = new Remote('https://example.myshopify.com/admin/api/2024-07/products.json', [
                'test' => true,
            ]);
            $remote->info['http_code'] = 403;
            $remote->content = json_encode([
                'errors' => '[API] This action requires merchant approval for read_products scope.',
            ]);

            return $remote;
        }
    };

    expect(fn () => $shopify->products())
        ->toThrow(
            RuntimeException::class,
            'Shopify Admin products request failed with status 403: [API] This action requires merchant approval for read_products scope.'
        );
});
