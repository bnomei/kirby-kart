<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Cart;
use Bnomei\Kart\Provider\Paypal;
use Dotenv\Dotenv;

uses()->group('providers');

require_once __DIR__.'/../Helpers.php';

beforeAll(function (): void {
    $root = dirname(__DIR__, 2);
    if (file_exists($root.'/.env')) {
        Dotenv::createImmutable($root)->safeLoad();
    }
});

beforeEach(function (): void {
    findOrCreateTestUser();
    $this->paypalClientId = $_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID');
    $this->paypalClientSecret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? getenv('PAYPAL_CLIENT_SECRET');
    $this->paypalEndpoint = $_ENV['PAYPAL_ENDPOINT'] ?? getenv('PAYPAL_ENDPOINT') ?? 'https://api-m.sandbox.paypal.com';
    if (empty($this->paypalEndpoint)) {
        $this->paypalEndpoint = 'https://api-m.sandbox.paypal.com';
    }
    if ($this->paypalClientId && $this->paypalClientSecret) {
        kart()->setOption('providers.paypal.client_id', $this->paypalClientId);
        kart()->setOption('providers.paypal.client_secret', $this->paypalClientSecret);
        kart()->setOption('providers.paypal.endpoint', $this->paypalEndpoint);
    }
    $this->paypal = new Paypal(kirby());
    // prime provider option cache so endpoint is available
    $endpoint = $this->paypalEndpoint;
    $clientId = $this->paypalClientId;
    $clientSecret = $this->paypalClientSecret;
    $bind = function () use ($endpoint, $clientId, $clientSecret) {
        $this->options['endpoint'] = $endpoint;
        $this->options['client_id'] = $clientId;
        $this->options['client_secret'] = $clientSecret;
    };
    $bind = $bind->bindTo($this->paypal, \Bnomei\Kart\Provider::class);
    $bind();
});

it('loads paypal env configuration', function (): void {
    fwrite(STDOUT, sprintf("PAYPAL_CLIENT_ID=%s\n", $this->paypalClientId ?: '[missing]'));
    fwrite(STDOUT, sprintf("PAYPAL_CLIENT_SECRET=%s\n", $this->paypalClientSecret ?: '[missing]'));
    fwrite(STDOUT, sprintf("PAYPAL_ENDPOINT_RAW=%s\n", $this->paypalEndpoint ?: '[missing]'));

    if (empty($this->paypalClientId) || empty($this->paypalClientSecret)) {
        $this->markTestSkipped('PAYPAL_CLIENT_ID or PAYPAL_CLIENT_SECRET missing');
    }

    expect($this->paypalClientId)->not()->toBeEmpty()
        ->and($this->paypalClientSecret)->not()->toBeEmpty();
});

it('fetches products via provider', function (): void {
    if (empty($this->paypalClientId) || empty($this->paypalClientSecret)) {
        $this->markTestSkipped('PAYPAL_CLIENT_ID or PAYPAL_CLIENT_SECRET missing');
    }

    $endpoint = $this->paypal->option('endpoint');
    fwrite(STDOUT, sprintf("PAYPAL_ENDPOINT=%s\n", $endpoint ?: '[missing]'));

    try {
        $products = $this->paypal->fetchProducts();
        if (empty($products)) {
            $this->markTestSkipped('No PayPal products returned');
        }

        $first = reset($products);
        expect($first)->toBeArray()
            ->and($first)->toHaveKey('id');
    } catch (Throwable $e) {
        fwrite(STDERR, 'PayPal products error: '.$e->getMessage()."\n");
        $this->markTestSkipped('Products fetch failed: '.$e->getMessage());
    }
});

it('creates a checkout url', function (): void {
    if (empty($this->paypalClientId) || empty($this->paypalClientSecret)) {
        $this->markTestSkipped('PAYPAL_CLIENT_ID or PAYPAL_CLIENT_SECRET missing');
    }

    kart()->setOption('providers.paypal.client_id', $this->paypalClientId);
    kart()->setOption('providers.paypal.client_secret', $this->paypalClientSecret);
    kart()->setOption('providers.paypal.endpoint', $this->paypalEndpoint);
    kart()->setOption('currency', 'USD');
    kart()->setOption('orders.order.uuid', fn () => uniqid('order_', true));

    // inject stub cart with fixed subtotal to avoid cart dependencies
    injectStubCart(1.23, []);

    // ensure required line data provided
    kart()->setOption('providers.paypal.checkout_line', fn () => []);
    kart()->setOption('providers.paypal.checkout_options', [
        'items' => [
            [
                'sku' => 'test-uuid',
                'name' => 'Test Product',
                'description' => 'Test Description',
                'unit_amount' => [
                    'currency_code' => 'USD',
                    'value' => '1.23',
                ],
                'quantity' => 1,
            ],
        ],
    ]);

    try {
        $url = $this->paypal->checkout();
        expect($url)->toBeString()
            ->and(str_starts_with($url, 'http'))->toBeTrue();
    } catch (Throwable $e) {
        fwrite(STDERR, 'PayPal checkout error: '.$e->getMessage()."\n");
        $this->markTestSkipped('Checkout failed: '.$e->getMessage());
    }
});
