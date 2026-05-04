<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Models\OrderPage;
use Bnomei\Kart\Models\OrdersPage;
use Bnomei\Kart\Models\ProductPage;
use Kirby\Data\Yaml;

beforeEach(function (): void {
    kart()->setOption('provider', 'kirby');
});

it('has a blueprint from PHP', function (): void {
    expect(Yaml::encode(OrderPage::phpBlueprint()))->toMatchSnapshot();
});

it('uses the final total in the panel summary blueprint', function (): void {
    $reports = OrderPage::phpBlueprint()['tabs']['order']['sections']['stats']['reports'];

    expect($reports[1])->toMatchArray([
        'label' => 'bnomei.kart.total',
        'value' => '{{ page.formattedTotal }}',
        'info' => '{{ page.formattedSubtotal }} - {{ page.formattedDiscount }} + {{ page.formattedTax }}',
    ]);
});

it('keeps discounted totals separate from subtotals', function (): void {
    /** @var OrdersPage $orders */
    $orders = kart()->page('orders');

    /** @var OrderPage $o */
    $o = $orders->createOrder([
        'paymentComplete' => true,
        'items' => [
            [
                'key' => ['page://discounted-test-product'],
                'quantity' => 1,
                'price' => 985.0,
                'total' => 492.5,
                'subtotal' => 985.0,
                'tax' => 0,
                'discount' => 492.5,
            ],
        ],
    ]);

    expect($o->subtotal())->toBe(985.0)
        ->and($o->formattedSubtotal())->toBe('€985.00')
        ->and($o->discount())->toBe(492.5)
        ->and($o->formattedDiscount())->toBe('€492.50')
        ->and($o->tax())->toBe(0.0)
        ->and($o->formattedTax())->toBe('€0.00')
        ->and($o->sum())->toBe(985.0)
        ->and($o->formattedSum())->toBe('€985.00')
        ->and($o->total())->toBe(492.5)
        ->and($o->formattedTotal())->toBe('€492.50');
});

it('is an order page', function (): void {
    /** @var ProductPage $p */
    $p = kart()->products()->first();
    $p_sc = $p->salesCount();
    /** @var ProductPage $l */
    $l = kart()->products()->last();
    $l_sc = $p->salesCount();
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
        ->and($o->downloads())->toBeNull()
        ->and($o->download())->toBeNull()
        ->and($o->invoice())->toBeString()
        ->and($o->invoiceNumber())->toHaveLength(5)
        ->and($o->url())->toBeString(5)
        ->and($o->urlWithSignature())->toContain('?signature=')
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
        ->and($p->salesCount())->toBe(4)
        ->and($l->salesCount())->toBe(3)
        ->and($l->toKerbs())->toBeArray();

    // create zip file
    touch($o->root().'/hello.jpg');
    $zip = $o->createZipWithFiles([
        $o->root().'/hello.jpg',
    ], 'hello.zip');
    $o = $zip->parent(); // refresh
    expect($zip)->not()->toBeNull()
        ->and($o->isPayed())->toBeTrue()
        ->and($o->downloads())->not()->toBeNull()
        ->and($o->download())->not()->toBeNull();
});
