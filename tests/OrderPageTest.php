<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Kirby\Data\Yaml;

it('has a blueprint from PHP', function (): void {
    expect(Yaml::encode(OrderPage::phpBlueprint()))->toMatchSnapshot();
});

it('is an order page', function (): void {
    /** @var ProductPage $p */
    $p = kart()->products()->first();
    /** @var ProductPage $l */
    $l = kart()->products()->last();
    /** @var OrdersPage $orders */
    $orders = kart()->page('orders');

    /** @var OrderPage $o */
    $o = $orders->createOrder([
        'paymentComplete' => true,
        'items' => [
            [
                'key' => [$p->uuid()->toString()],
                'quantity' => 2,
                'price' => $p->price()->toInt() * 2,
                'total' => $p->price()->toInt() * 2 - 3 + 5,
                'subtotal' => $p->price()->toInt() * 2,
                'tax' => 5,
                'discount' => 3,
            ],
            [
                'key' => [$l->uuid()->toString()],
                'quantity' => 3,
                'price' => $l->price()->toInt() * 3 - 2,
                'total' => $l->price()->toInt() * 3 + 10,
                'subtotal' => $l->price()->toInt() * 3,
                'tax' => 10,
                'discount' => 2,
            ],
        ],
    ]);

    expect($o->isPayed())->toBeTrue()
        ->and($o->orderLines()->count())->toBe(2)
        ->and($o->download())->toBeNull()
        ->and($o->downloads())->toBeNull()
        ->and($o->invoice())->toBeString()
        ->and($o->invoiceNumber())->toHaveLength(5)
        ->and($o->tax())->toBe(15.0)
        ->and($o->formattedTax())->toBe('€15.00')
        ->and($o->sum())->toBe(75.0)
        ->and($o->formattedSum())->toBe('€75.00')
        ->and($o->sumtax())->toBe(87.0)
        ->and($o->formattedSumTax())->toBe('€87.00')
        ->and($o->subtotal())->toBe(75.0)
        ->and($o->formattedSubtotal())->toBe('€75.00')
        ->and($o->discount())->toBe(5.0)
        ->and($o->formattedDiscount())->toBe('€5.00')
        ->and($o->total())->toBe(87.0)
        ->and($o->formattedTotal())->toBe('€87.00')
        ->and($p->sold())->toBe(2)
        ->and($l->sold())->toBe(3);

    // create zip file
    touch($o->root().'/hello.jpg');
    expect($o->createZipWithFiles([
        $o->root().'/hello.jpg',
    ], 'hello.zip'))->not()->toBeNull()
        ->and($o->download())->not()->toBeNull()
        ->and($o->downloads())->not()->toBeNull();
});
