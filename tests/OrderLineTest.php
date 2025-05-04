<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\OrderLine;

it('can create orderlines', function (): void {
    $product = page('products')->children()->random(1)->first();

    $o = new OrderLine(
        $product->uuid()->toString(), // id would work as well but not uuid.id
        123.4,
        2,
        123.4 * 2 + 10.00 - 20.00,
        123.4 * 2,
        10.0,
        20.0,
        'yada',
    );

    expect($o)->toBeInstanceOf(OrderLine::class)
        ->and($o->id())->toBe($product->uuid()->toString()) // uuid as key for collections
        ->and($o->key())->toBe($o->id()) // Merx
        ->and($o->quantity())->toBe(2)
        ->and($o->doesNotExist())->toBeNull()
        ->and($o->licensekey())->toBe('yada')
        ->and($o->product()->id())->toBe($product->id())
        ->and($o->formattedPrice())->toBe('€123.40')
        ->and($o->formattedSubtotal())->toBe('€246.80')
        ->and($o->formattedDiscount())->toBe('€20.00')
        ->and($o->formattedTotal())->toBe('€236.80');
});
