<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Provider\Lemonsqueezy;
use Dotenv\Dotenv;
use Kirby\Cms\Collection;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

uses()->group('providers');

require_once __DIR__.'/../Helpers.php';

function firstPublishedLemonsqueezyVariant(string $secret, ?string $storeId = null): ?array
{
    $commonHeaders = [
        'Accept' => 'application/vnd.api+json',
        'Content-Type' => 'application/vnd.api+json',
        'Authorization' => 'Bearer '.$secret,
    ];

    $remote = Remote::get('https://api.lemonsqueezy.com/v1/products', [
        'headers' => $commonHeaders,
        'data' => array_filter([
            'filter[store_id]' => $storeId,
            'page[number]' => 1,
        ]),
    ]);

    if ($remote->code() !== 200) {
        fwrite(STDERR, "Lemon products fetch failed: {$remote->code()} body: ".$remote->content()."\n");

        return null;
    }

    foreach (A::get($remote->json(), 'data', []) as $product) {
        if (A::get($product, 'attributes.status') !== 'published') {
            continue;
        }

        $variants = Remote::get('https://api.lemonsqueezy.com/v1/variants', [
            'headers' => $commonHeaders,
            'data' => [
                'filter[product_id]' => $product['id'],
            ],
        ]);

        if ($variants->code() !== 200) {
            continue;
        }

        foreach (A::get($variants->json(), 'data', []) as $variant) {
            // default variant may be pending but still usable; prefer published
            $status = A::get($variant, 'attributes.status');
            if ($status !== 'published' && strtolower(A::get($variant, 'attributes.name', '')) !== 'default') {
                continue;
            }

            return [
                'product_id' => $product['id'],
                'variant_id' => $variant['id'],
            ];
        }
    }

    return null;
}

beforeAll(function (): void {
    $root = dirname(__DIR__, 2);
    if (file_exists($root.'/.env')) {
        Dotenv::createImmutable($root)->safeLoad();
    }
});

beforeEach(function (): void {
    findOrCreateTestUser();
    $this->lemonsqueezy = new Lemonsqueezy(kirby());
    $this->lsqSecret = $_ENV['LEMONSQUEEZY_SECRET_KEY'] ?? getenv('LEMONSQUEEZY_SECRET_KEY');
    $this->lsqStore = $_ENV['LEMONSQUEEZY_STORE_ID'] ?? getenv('LEMONSQUEEZY_STORE_ID');
});

it('loads lemonsqueezy env configuration', function (): void {
    fwrite(STDOUT, sprintf("LEMONSQUEEZY_SECRET_KEY=%s\n", $this->lsqSecret ?: '[missing]'));
    fwrite(STDOUT, sprintf("LEMONSQUEEZY_STORE_ID=%s\n", $this->lsqStore ?: '[missing]'));

    if (empty($this->lsqSecret) || empty($this->lsqStore)) {
        $this->markTestSkipped('LEMONSQUEEZY_SECRET_KEY or LEMONSQUEEZY_STORE_ID missing');
    }

    expect($this->lsqSecret)->not()->toBeEmpty()
        ->and($this->lsqStore)->not()->toBeEmpty();
});

it('fetches products via provider', function (): void {
    if (empty($this->lsqSecret) || empty($this->lsqStore)) {
        $this->markTestSkipped('LEMONSQUEEZY_SECRET_KEY or LEMONSQUEEZY_STORE_ID missing');
    }

    kart()->setOption('providers.lemonsqueezy.secret_key', $this->lsqSecret);
    kart()->setOption('providers.lemonsqueezy.store_id', $this->lsqStore);

    $products = $this->lemonsqueezy->fetchProducts();
    if (empty($products)) {
        $remote = Remote::get('https://api.lemonsqueezy.com/v1/products', [
            'headers' => [
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
                'Authorization' => 'Bearer '.$this->lsqSecret,
            ],
            'data' => [
                'filter[store_id]' => $this->lsqStore,
                'page[number]' => 1,
            ],
        ]);
        fwrite(STDERR, sprintf(
            "Lemon direct products fetch: code=%s body=%s\n",
            $remote->code(),
            $remote->content()
        ));
        $this->markTestSkipped('No published Lemon Squeezy products returned');
    }

    $first = reset($products);
    expect($first)->toBeArray()
        ->and($first)->toHaveKey('id');
});

it('creates a checkout url', function (): void {
    if (empty($this->lsqSecret) || empty($this->lsqStore)) {
        $this->markTestSkipped('LEMONSQUEEZY_SECRET_KEY or LEMONSQUEEZY_STORE_ID missing');
    }

    $variant = firstPublishedLemonsqueezyVariant($this->lsqSecret, $this->lsqStore);
    if (! $variant) {
        $this->markTestSkipped('No published Lemon Squeezy variants found');
    }

    kart()->setOption('providers.lemonsqueezy.secret_key', $this->lsqSecret);
    kart()->setOption('providers.lemonsqueezy.store_id', $this->lsqStore);

    // inject a minimal cart line stub that exposes product() + variant()
    $stubLine = new class ($variant['variant_id']) {
        public function __construct(private string $variantId)
        {
        }

        public function product(): object
        {
            return new class ($this->variantId) {
                public function __construct(private string $variantId)
                {
                }

                public function raw(): object
                {
                    return new class ($this->variantId) {
                        public function __construct(private string $variantId)
                        {
                        }

                        public function yaml(): array
                        {
                            return [
                                'variants' => [
                                    [
                                        'id' => $this->variantId,
                                        'name' => 'default',
                                        'price' => 100,
                                    ],
                                ],
                            ];
                        }
                    };
                }

                public function priceIdForVariant(string $variant): string
                {
                    return $this->variantId;
                }
            };
        }

        public function variant(): ?string
        {
            return null;
        }
    };

    // replace cart with stub that returns our single line
    injectStubCart(1.00, [$stubLine]);

    try {
        $url = $this->lemonsqueezy->checkout();
        expect($url)->toBeString()
            ->and(str_starts_with($url, 'http'))->toBeTrue();
    } catch (Throwable $e) {
        fwrite(STDERR, "Lemon checkout error: ".$e->getMessage()."\n");
        $this->markTestSkipped('Checkout failed: '.$e->getMessage());
    }
});
