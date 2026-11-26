<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\ProductSyncException;
use Bnomei\Kart\Provider\Chargebee;
use Bnomei\Kart\Provider\Fastspring;
use Bnomei\Kart\Provider\Gumroad;
use Bnomei\Kart\Provider\Invoiceninja;
use Bnomei\Kart\Provider\Lemonsqueezy;
use Bnomei\Kart\Provider\Paddle;
use Bnomei\Kart\Provider\Paypal;
use Bnomei\Kart\Provider\Polar;
use Bnomei\Kart\Provider\Shopify;
use Bnomei\Kart\Provider\Snipcart;
use Bnomei\Kart\Provider\Stripe;
use Kirby\Cache\Value;
use Kirby\Filesystem\F;
use Kirby\Http\Remote;

trait ProductSyncTestTransport
{
    public array $fixtureOptions = [];

    public array $responses = [];

    public array $requestUrls = [];

    public function option(string $key, bool $resolveCallables = true): mixed
    {
        return $this->fixtureOptions[$key] ?? parent::option($key, $resolveCallables);
    }

    protected function sendProductSyncRequest(string $method, string $url, array $options): Remote
    {
        $response = array_shift($this->responses) ?? throw new Error('Unexpected catalog request');
        if ($response instanceof Exception) {
            throw $response;
        }

        [$status, $body, $headers] = $response + [2 => []];
        $remote = parent::sendProductSyncRequest($method, $url, $options + ['test' => true]);
        $this->requestUrls[] = $remote->options()['url'];
        $remote->info['http_code'] = $status;
        $remote->content = json_encode($body, JSON_THROW_ON_ERROR);
        $remote->headers = $headers;

        return $remote;
    }
}

dataset('catalog providers', [
    'Chargebee' => [fn () => new class(kirby()) extends Chargebee
    {
        use ProductSyncTestTransport;
    }, ['site', 'api_key'], ['list' => []], 'item prices'],
    'FastSpring' => [fn () => new class(kirby()) extends Fastspring
    {
        use ProductSyncTestTransport;
    }, ['username', 'password'], ['products' => []], 'products'],
    'Gumroad' => [fn () => new class(kirby()) extends Gumroad
    {
        use ProductSyncTestTransport;
    }, ['access_token'], ['success' => true, 'products' => []], 'products'],
    'Invoice Ninja' => [fn () => new class(kirby()) extends Invoiceninja
    {
        use ProductSyncTestTransport;
    }, ['token'], ['data' => []], 'products'],
    'Lemon Squeezy' => [fn () => new class(kirby()) extends Lemonsqueezy
    {
        use ProductSyncTestTransport;
    }, ['secret_key'], ['data' => []], 'products'],
    'Paddle' => [fn () => new class(kirby()) extends Paddle
    {
        use ProductSyncTestTransport;
    }, ['secret_key'], ['data' => []], 'products'],
    'PayPal' => [fn () => new class(kirby()) extends Paypal
    {
        use ProductSyncTestTransport;
    }, ['client_id', 'client_secret'], ['total_items' => 0, 'total_pages' => 0], 'an OAuth access token'],
    'Polar' => [fn () => new class(kirby()) extends Polar
    {
        use ProductSyncTestTransport;
    }, ['access_token'], ['items' => []], 'products'],
    'Shopify' => [fn () => new class(kirby()) extends Shopify
    {
        use ProductSyncTestTransport;
    }, ['store_domain', 'client_id', 'client_secret'], ['products' => []], 'an Admin API access token'],
    'Snipcart' => [fn () => new class(kirby()) extends Snipcart
    {
        use ProductSyncTestTransport;
    }, ['secret_key'], ['items' => [], 'totalItems' => 0], 'products'],
    'Stripe' => [fn () => new class(kirby()) extends Stripe
    {
        use ProductSyncTestTransport;
    }, ['secret_key'], ['data' => [], 'has_more' => false], 'products'],
]);

it('distinguishes failed sync from a valid empty catalog', function (Closure $make, array $required, array $empty, string $resource): void {
    $provider = $make();
    $cache = $provider->cache();
    $cache->flush();
    try {
        $credentials = array_fill_keys($required, 'fixture-value');
        $provider->fixtureOptions = $credentials;
        foreach ($required as $key) {
            $provider->fixtureOptions[$key] = ' ';
            expect(fn () => $provider->sync(ContentPageEnum::PRODUCTS))
                ->toThrow(ProductSyncException::class, 'bnomei.kart.providers.'.$provider->name().'.'.$key);
            $provider->fixtureOptions[$key] = $credentials[$key];
        }
        expect($cache->get('products'))->toBeNull()->and($cache->get('updatedAt-products'))->toBeNull();

        $old = ['fixture' => ['id' => 'fixture', 'content' => ['price' => 19.75]]];
        $oldTime = '2025-01-10 12:00:00';
        $cache->set('products', $old);
        $cache->set('updatedAt-products', $oldTime);
        expect($provider->products())->toBe($old);
        $provider->responses = [[401, ['error' => 'Rejected fixture-value']]];
        expect(fn () => $provider->sync(ContentPageEnum::PRODUCTS))
            ->toThrow(ProductSyncException::class, $provider->title().' denied access to '.$resource.'. Check the API credentials and their read permissions, then try again.');
        expect($provider->products())->toBe($old)
            ->and($cache->get('products'))->toBe($old)
            ->and($cache->get('updatedAt-products'))->toBe($oldTime);

        $provider->responses = match ($provider->name()) {
            'paypal', 'shopify' => [[200, ['access_token' => 'fixture-access-token']], [200, $empty]],
            default => [[200, $empty]],
        };
        $provider->sync(ContentPageEnum::PRODUCTS);
        expect($provider->products())->toBe([])
            ->and($cache->get('products'))->toBe([])
            ->and($cache->get('updatedAt-products'))->toBeString()->not->toBe($oldTime);
    } finally {
        $cache->flush();
    }
})->with('catalog providers');

it('serves a stale price after failed refresh and invalidates derived data after recovery', function (): void {
    $provider = new class(kirby()) extends Stripe
    {
        use ProductSyncTestTransport;
    };
    $provider->fixtureOptions = ['secret_key' => 'fixture'];
    $cache = $provider->cache();
    $variants = kirby()->cache('bnomei.kart.variants');
    $expire = kart()->option('expire');
    kart()->setOption('expire', 1);
    $old = ['fixture' => ['id' => 'fixture', 'content' => ['price' => 19.75]]];
    $oldTime = '2025-01-10 12:00:00';

    try {
        // Model a snapshot that was published more than one freshness period ago.
        F::write($cache->root().'/products.cache', (new Value($old, created: time() - 120))->toJson());
        $cache->set('updatedAt-products', $oldTime);
        $variants->set('variants', ['finish:matte']);
        $provider->responses = [[401, []], [503, []]];

        expect(fn () => $provider->sync())->toThrow(ProductSyncException::class, 'Stripe denied access');
        expect($provider->products())->toBe($old)
            ->and($provider->responses)->toBe([])
            ->and($cache->get('updatedAt-products'))->toBe($oldTime)
            ->and(kart()->allVariants())->toBe(['finish:matte']);

        $provider->responses = [[200, ['data' => [], 'has_more' => false]]];
        $provider->sync();
        expect($cache->get('products'))->toBe([])
            ->and($cache->get('updatedAt-products'))->not->toBe($oldTime)
            ->and(kart()->allVariants())->not->toContain('finish:matte');
    } finally {
        kart()->setOption('expire', $expire);
        $cache->flush();
        $variants->flush();
    }
});

it('publishes all Paddle pages and stops when has_more is false even with a next URL', function (): void {
    $provider = new class(kirby()) extends Paddle
    {
        use ProductSyncTestTransport;
    };
    $provider->fixtureOptions = ['secret_key' => 'fixture'];
    $provider->responses = [
        [200, [
            'data' => [['id' => 'pro_1', 'name' => 'Cup', 'created_at' => '2025-01-10T12:00:00Z']],
            'meta' => ['pagination' => ['has_more' => true, 'next' => 'https://example.test/products?after=pro_1']],
        ]],
        [200, [
            'data' => [['id' => 'pro_2', 'name' => 'Plate', 'created_at' => '2025-01-11T12:00:00Z']],
            'meta' => ['pagination' => ['has_more' => false, 'next' => 'https://example.test/products?after=pro_2']],
        ]],
    ];

    try {
        $provider->sync(ContentPageEnum::PRODUCTS);
        expect(array_keys($provider->products()))->toBe(['pro_1', 'pro_2']);
        parse_str(parse_url($provider->requestUrls[0], PHP_URL_QUERY) ?? '', $first);
        parse_str(parse_url($provider->requestUrls[1], PHP_URL_QUERY) ?? '', $second);
        expect($first['include'] ?? null)->toBe('prices')
            ->and($first)->not->toHaveKey('after')
            ->and($second['after'] ?? null)->toBe('pro_1')
            ->and($second['include'] ?? null)->toBe('prices');
    } finally {
        $provider->cache()->flush();
    }
});

it('does not publish an incomplete refresh', function (Closure $make, array $options, array $responses, string $error): void {
    $provider = $make();
    $provider->fixtureOptions = $options;
    $provider->responses = $responses;
    $cache = $provider->cache();
    $old = ['fixture' => ['id' => 'fixture', 'content' => ['price' => 19.75]]];
    $cache->set('products', $old);
    $cache->set('updatedAt-products', '2025-01-10 12:00:00');

    try {
        expect(fn () => $provider->sync(ContentPageEnum::PRODUCTS))->toThrow(ProductSyncException::class, $error);
        expect($cache->get('products'))->toBe($old)
            ->and($cache->get('updatedAt-products'))->toBe('2025-01-10 12:00:00');
    } finally {
        $cache->flush();
    }
})->with('incomplete catalog refreshes');

dataset('incomplete catalog refreshes', [
    'FastSpring details fail' => [
        fn () => new class(kirby()) extends Fastspring
        {
            use ProductSyncTestTransport;
        },
        ['username' => 'fixture', 'password' => 'fixture'],
        [[200, ['products' => ['one']]], [500, []]],
        'could not load product details (HTTP 500)',
    ],
    'Lemon Squeezy variant request fails' => [
        fn () => new class(kirby()) extends Lemonsqueezy
        {
            use ProductSyncTestTransport;
        },
        ['secret_key' => 'fixture'],
        [[200, ['data' => [['id' => '123', 'attributes' => ['status' => 'published']]]]], [403, []]],
        'denied access to variants',
    ],
    'Paddle says there are more products without providing a cursor' => [
        fn () => new class(kirby()) extends Paddle
        {
            use ProductSyncTestTransport;
        },
        ['secret_key' => 'fixture'],
        [[200, ['data' => [['id' => 'pro_fixture']], 'meta' => ['pagination' => ['has_more' => true]]]]],
        'Paddle returned an incomplete products page.',
    ],
    'PayPal detail request fails' => [
        fn () => new class(kirby()) extends Paypal
        {
            use ProductSyncTestTransport;
        },
        ['client_id' => 'fixture', 'client_secret' => 'fixture'],
        [[200, ['access_token' => 'fixture']], [200, ['products' => [['id' => 'PROD-1']]]], [403, []]],
        'denied access to product details',
    ],
    'Polar second page fails' => [
        fn () => new class(kirby()) extends Polar
        {
            use ProductSyncTestTransport;
        },
        ['access_token' => 'fixture'],
        [[200, ['items' => [['id' => '1']], 'pagination' => ['max_page' => 2]]], [503, []]],
        'could not load products (HTTP 503)',
    ],
    'Shopify second page fails' => [
        fn () => new class(kirby()) extends Shopify
        {
            use ProductSyncTestTransport;
        },
        ['store_domain' => 'example.myshopify.com', 'client_id' => 'fixture', 'client_secret' => 'fixture', 'metafields' => []],
        [
            [200, ['access_token' => 'fixture']],
            [200, ['products' => [['id' => 1]]], ['Link' => '<https://example.myshopify.com/products.json?page_info=next>; rel="next"']],
            [200, ['access_token' => 'fixture']],
            [503, []],
        ],
        'could not load Admin products (HTTP 503)',
    ],
    'Stripe prices fail after products load' => [
        fn () => new class(kirby()) extends Stripe
        {
            use ProductSyncTestTransport;
        },
        ['secret_key' => 'fixture'],
        [[200, ['data' => [['id' => 'prod_fixture']]]], [403, []]],
        'denied access to prices',
    ],
    'Stripe returns a malformed success' => [
        fn () => new class(kirby()) extends Stripe
        {
            use ProductSyncTestTransport;
        },
        ['secret_key' => 'fixture'],
        [[200, ['data' => new stdClass]]],
        'returned an invalid products response',
    ],
    'Stripe transport fails' => [
        fn () => new class(kirby()) extends Stripe
        {
            use ProductSyncTestTransport;
        },
        ['secret_key' => 'fixture'],
        [new RuntimeException('Failed request with fixture-value')],
        'Could not reach Stripe to load products. Check the connection and try again.',
    ],
]);
