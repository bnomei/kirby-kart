<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Provider\Lemonsqueezy;

require_once __DIR__.'/Helpers.php';

beforeEach(function (): void {
    findOrCreateTestUser();
    $this->provider = new Lemonsqueezy(kirby());
    $this->method = new ReflectionMethod(Lemonsqueezy::class, 'orderMatchesCheckout');
});

function lemonsqueezyOrderForBinding(array $overrides = []): array
{
    return [
        'id' => '42',
        'attributes' => array_replace_recursive([
            'identifier' => 'ord_123',
            'user_email' => 'customer@kart.test',
            'status' => 'paid',
            'refunded' => false,
            'first_order_item' => [
                'variant_id' => 123,
            ],
        ], $overrides),
    ];
}

it('matches success redirect data to the current checkout binding', function (): void {
    $binding = [
        'cart_hash' => kart()->cart()->hash(),
        'variant_id' => '123',
        'email' => 'customer@kart.test',
    ];

    $redirect = [
        'order_identifier' => 'ord_123',
        'email' => 'customer@kart.test',
    ];

    expect($this->method->invoke($this->provider, lemonsqueezyOrderForBinding(), $binding, $redirect))->toBeTrue();
});

it('rejects success redirects for a different order or checkout binding', function (): void {
    $binding = [
        'cart_hash' => kart()->cart()->hash(),
        'variant_id' => '123',
        'email' => 'customer@kart.test',
    ];

    $redirect = [
        'order_identifier' => 'ord_123',
        'email' => 'customer@kart.test',
    ];

    expect($this->method->invoke($this->provider, lemonsqueezyOrderForBinding([
        'first_order_item' => [
            'variant_id' => 456,
        ],
    ]), $binding, $redirect))->toBeFalse()
        ->and($this->method->invoke($this->provider, lemonsqueezyOrderForBinding(), $binding, [
            'order_identifier' => 'ord_456',
            'email' => 'customer@kart.test',
        ]))->toBeFalse()
        ->and($this->method->invoke($this->provider, lemonsqueezyOrderForBinding(), [
            'cart_hash' => 'old-cart',
            'variant_id' => '123',
            'email' => 'customer@kart.test',
        ], $redirect))->toBeFalse();
});
