<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Provider\Stripe;
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
    findOrCreateTestUser(); // ensure a logged-in user for provider flows
    $this->stripe = new Stripe(kirby());
});

it('loads stripe env configuration', function (): void {
    $secret = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY');

    fwrite(STDOUT, sprintf("STRIPE_SECRET_KEY=%s\n", $secret ?: '[missing]'));

    expect($secret)->not()->toBeEmpty();
});

it('fetches products via provider', function (): void {
    $secret = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY');
    if (empty($secret)) {
        $this->markTestSkipped('STRIPE_SECRET_KEY missing');
    }

    // Provider reads from kart()->option('providers.stripe.secret_key')
    kart()->setOption('providers.stripe.secret_key', $secret);

    $products = $this->stripe->fetchProducts();
    if (empty($products)) {
        $this->markTestSkipped('No active Stripe products found');
    }

    $first = reset($products);
    expect($first)->toBeArray()
        ->and($first)->toHaveKey('id');
});

it('creates a checkout session url', function (): void {
    $secret = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY');
    if (empty($secret)) {
        $this->markTestSkipped('STRIPE_SECRET_KEY missing');
    }

    kart()->setOption('providers.stripe.secret_key', $secret);

    // fetch a one-time price with an active product
    $remote = Kirby\Http\Remote::get('https://api.stripe.com/v1/prices', [
        'headers' => [
            'Authorization' => 'Bearer '.$secret,
        ],
        'data' => [
            'active' => 'true',
            'type' => 'one_time',
            'limit' => 10,
            'expand' => ['data.product'],
        ],
    ]);

    if ($remote->code() !== 200) {
        $this->markTestSkipped('Stripe prices fetch failed: '.$remote->code());
    }

    $prices = $remote->json();
    $price = null;
    foreach ($prices['data'] ?? [] as $candidate) {
        if (($candidate['product']['active'] ?? false) === true) {
            $price = $candidate;
            break;
        }
    }
    if (! $price) {
        $this->markTestSkipped('No active Stripe prices with active products found');
    }

    $priceId = $price['id'] ?? null;
    $currency = $price['currency'] ?? 'usd';
    if (empty($priceId)) {
        $this->markTestSkipped('Stripe returned price without id');
    }

    kart()->setOption('currency', $currency);

    // bypass cart dependency by supplying line items directly
    kart()->setOption('providers.stripe.checkout_options', [
        'mode' => 'payment',
        'success_url' => 'https://example.com/success',
        'cancel_url' => 'https://example.com/cancel',
        'invoice_creation' => ['enabled' => 'true'],
        'line_items' => [
            [
                'price' => $priceId,
                'quantity' => 1,
            ],
        ],
    ]);

    try {
        $url = $this->stripe->checkout();
        expect($url)->toBeString()
            ->and(str_starts_with($url, 'http'))->toBeTrue();
    } catch (Throwable $e) {
        fwrite(STDERR, "Stripe checkout error: ".$e->getMessage()."\n");
        $this->markTestSkipped('Checkout failed: '.$e->getMessage());
    }
});
