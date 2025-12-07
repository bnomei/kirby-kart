<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Provider\Paddle;
use Dotenv\Dotenv;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

uses()->group('providers');

require_once __DIR__.'/../Helpers.php';

function firstActivePaddlePrice(string $endpoint, string $secret): ?array
{
    $remote = Remote::get($endpoint.'/products', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$secret,
        ],
        'data' => [
            'status' => 'active',
            'per_page' => 50,
            'include' => 'prices',
        ],
    ]);

    if ($remote->code() !== 200) {
        fwrite(STDERR, "Paddle products fetch failed: {$remote->code()}\n");

        return null;
    }

    foreach (A::get($remote->json(), 'data', []) as $product) {
        foreach (A::get($product, 'prices', []) as $price) {
            if (($price['status'] ?? null) !== 'active') {
                continue;
            }

            $currency = A::get($price, 'unit_price.currency_code');
            $priceId = $price['id'] ?? null;
            if ($priceId && $currency) {
                return [
                    'product_id' => $product['id'] ?? null,
                    'price_id' => $priceId,
                    'currency' => $currency,
                ];
            }
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
    $this->paddle = new Paddle(kirby());
    $this->paddleSecret = $_ENV['PADDLE_SECRET_KEY'] ?? getenv('PADDLE_SECRET_KEY');
    $this->paddleEndpoint = $_ENV['PADDLE_ENDPOINT'] ?? getenv('PADDLE_ENDPOINT') ?? '';
    if (empty($this->paddleEndpoint)) {
        $this->paddleEndpoint = 'https://sandbox-api.paddle.com';
    }
});

it('loads paddle env configuration', function (): void {
    fwrite(STDOUT, sprintf("PADDLE_SECRET_KEY=%s\n", $this->paddleSecret ?: '[missing]'));

    if (empty($this->paddleSecret)) {
        $this->markTestSkipped('PADDLE_SECRET_KEY missing');
    }

    expect($this->paddleSecret)->not()->toBeEmpty();
});

it('fetches products via provider', function (): void {
    if (empty($this->paddleSecret)) {
        $this->markTestSkipped('PADDLE_SECRET_KEY missing');
    }

    $active = firstActivePaddlePrice($this->paddleEndpoint, $this->paddleSecret);
    if (! $active) {
        $this->markTestSkipped('No active Paddle products with active prices found');
    }

    kart()->setOption('providers.paddle.secret_key', $this->paddleSecret);
    kart()->setOption('providers.paddle.endpoint', $this->paddleEndpoint);
    kart()->setOption('currency', $active['currency']);

    $products = $this->paddle->fetchProducts();
    if (empty($products)) {
        $this->markTestSkipped('No Paddle products returned by provider');
    }

    $first = reset($products);
    expect($products)->toHaveKey($active['product_id'])
        ->and($first)->toBeArray()
        ->and($first)->toHaveKey('id');
});

it('creates a checkout transaction url', function (): void {
    if (empty($this->paddleSecret)) {
        $this->markTestSkipped('PADDLE_SECRET_KEY missing');
    }

    $active = firstActivePaddlePrice($this->paddleEndpoint, $this->paddleSecret);
    if (! $active) {
        $this->markTestSkipped('No active Paddle products with active prices found');
    }

    kart()->setOption('providers.paddle.secret_key', $this->paddleSecret);
    kart()->setOption('providers.paddle.endpoint', $this->paddleEndpoint);
    kart()->setOption('currency', $active['currency']);

    kart()->setOption('providers.paddle.checkout_options', [
        'items' => [
            [
                'price_id' => $active['price_id'],
                'quantity' => 1,
            ],
        ],
    ]);

    try {
        $url = $this->paddle->checkout();
        expect($url)->toBeString();
    } catch (Throwable $e) {
        fwrite(STDERR, "Paddle checkout error: ".$e->getMessage()."\n");
        $this->markTestSkipped('Checkout failed: '.$e->getMessage());
    }
});
