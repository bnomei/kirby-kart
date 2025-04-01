<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Cart;
use Bnomei\Kart\Wishlist;

it('has a wishlist that is just like a cart', function (): void {
    $w = new Wishlist('wishlist');
    expect($w)->toBeInstanceOf(Cart::class)
        ->and($w->id())->toBe('wishlist');
});
