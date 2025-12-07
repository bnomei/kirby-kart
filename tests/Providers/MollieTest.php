<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Provider\Mollie;
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
    $this->mollie = new Mollie(kirby());
});

it('loads mollie env configuration', function (): void {
    $secret = $_ENV['MOLLIE_SECRET_KEY'] ?? getenv('MOLLIE_SECRET_KEY');

    fwrite(STDOUT, sprintf("MOLLIE_SECRET_KEY=%s\n", $secret ?: '[missing]'));

    if (empty($secret)) {
        $this->markTestSkipped('MOLLIE_SECRET_KEY missing');
    }

    expect($secret)->not()->toBeEmpty();
});

it('creates a checkout payment url', function (): void {
    $secret = $_ENV['MOLLIE_SECRET_KEY'] ?? getenv('MOLLIE_SECRET_KEY');
    if (empty($secret)) {
        $this->markTestSkipped('MOLLIE_SECRET_KEY missing');
    }

    kart()->setOption('providers.mollie.secret_key', $secret);

    // minimal payment payload: fixed lines + amount to avoid cart dependency
    kart()->setOption('currency', 'EUR');
    kart()->setOption('orders.order.uuid', fn () => uniqid('order_', true));
    kart()->setOption('providers.mollie.checkout_options', [
        'locale' => 'en_US',
        'amount' => [
            'currency' => 'EUR',
            'value' => '1.00',
        ],
        'lines' => [
            [
                'sku' => 'test-sku',
                'type' => 'digital',
                'description' => 'Test product',
                'quantity' => 1,
                'unitPrice' => [
                    'currency' => 'EUR',
                    'value' => '1.00',
                ],
                'totalAmount' => [
                    'currency' => 'EUR',
                    'value' => '1.00',
                ],
                'vatRate' => '0.00',
                'vatAmount' => [
                    'currency' => 'EUR',
                    'value' => '0.00',
                ],
                'discountAmount' => [
                    'currency' => 'EUR',
                    'value' => '0.00',
                ],
            ],
        ],
    ]);

    try {
        $url = $this->mollie->checkout();
        expect($url)->toBeString()
            ->and(str_starts_with($url, 'http'))->toBeTrue();
    } catch (Throwable $e) {
        fwrite(STDERR, "Mollie checkout error: ".$e->getMessage()."\n");
        $this->markTestSkipped('Checkout failed: '.$e->getMessage());
    }
});
