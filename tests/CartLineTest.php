<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\CartLine;
use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Models\StocksPage;

beforeEach(function (): void {
    $this->cartLine = new CartLine(
        page('products')->children()->last(), 2
    );
});

it('can create a CartLine instance', function (): void {
    expect($this->cartLine)->toBeInstanceOf(CartLine::class);
});

it('increments the quantity correctly', function (): void {
    $this->cartLine->increment(3);
    expect($this->cartLine->quantity())->toBe(5); // 2 (initial) + 3
});

it('decrements the quantity correctly', function (): void {
    $this->cartLine->decrement(1);
    expect($this->cartLine->quantity())->toBe(1); // 2 (initial) - 1
});

it('respects a minimum quantity of 0 when decrementing', function (): void {
    $this->cartLine->decrement(10); // Exceeds the current quantity
    expect($this->cartLine->quantity())->toBe(0); // Should not go below 0
});

it('can retrieve the correct id value', function (): void {
    expect($this->cartLine->id())->toBe($this->cartLine->product()->uuid()->id())
        ->and($this->cartLine->key())->toBe($this->cartLine->id());
});

it('can calculate the correct subtotal', function (): void {
    // Without product price being resolved, subtotal should be 0
    expect($this->cartLine->subtotal())->toBe($this->cartLine->quantity() * $this->cartLine->price());
});

it('can convert the CartLine to an array', function (): void {
    expect($this->cartLine->toArray())->toBe([
        'quantity' => 2, // Initial quantity
    ]);
});

it('can retrieve the formatted price correctly', function (): void {
    $formattedPrice = number_format($this->cartLine->price(), 2);
    expect($this->cartLine->formattedPrice())->toBe('€'.$formattedPrice);
});

it('can calculate the formatted subtotal correctly', function (): void {
    $subtotal = $this->cartLine->quantity() * $this->cartLine->price();
    $formattedSubtotal = number_format($subtotal, 2);
    expect($this->cartLine->formattedSubtotal())->toBe('€'.$formattedSubtotal);
});

it('checks if the product has stock with the given quantity', function (): void {
    kart()->setOption('stocks.queue', false);
    $this->cartLine->product()->updateStock(5, true); // Set product stock to 5
    expect($this->cartLine->product()->stock())->toBe(5)
        ->and($this->cartLine->hasStockForQuantity())->toBeTrue();

    $this->cartLine->increment($this->cartLine->product()->stock());
    expect($this->cartLine->hasStockForQuantity())->toBeFalse();

    // infinite stock
    /** @var StocksPage $stocks */
    $stocks = kart()->page(ContentPageEnum::STOCKS);
    kirby()->impersonate('kirby', function () use ($stocks): void {
        // @see ONE_INFINITE_STOCK
        $stocks->stockPages($this->cartLine->product())->first()?->delete(true); // none = infinite
    });
    expect($this->cartLine->hasStockForQuantity())->toBeTrue()
        ->and($this->cartLine->product()->stock())->toBe('∞');
});

it('can be created from a product uuid.id', function (): void {
    $p = page('products')->children()->random(1)->first();
    $c = new CartLine($p->uuid()->id());
    expect($c->product())->toBe($p);
});

it('honors the max amount of product per order (global and in product)', function (): void {
    kart()->setOption('stocks.queue', false);
    kart()->setOption('orders.order.maxapo', 5);

    expect($this->cartLine->product()->maxAmountPerOrder())->toBe(5)
        ->and($this->cartLine->product()->maxapo()->isEmpty())->toBeTrue();
    $this->cartLine->product()->updateStock(10, true);

    $this->cartLine->setQuantity(4);
    expect($this->cartLine->hasStockForQuantity())->toBeTrue();
    $this->cartLine->setQuantity(6);
    expect($this->cartLine->quantity())->toBe(5); // maxapo HIT

    kirby()->impersonate('kirby');
    $this->cartLine->product()->update([
        'maxapo' => 15,
    ]);
    $this->cartLine->product(true); // refresh because of update
    expect($this->cartLine->product()->maxapo()->toInt())->toBe(15)
        ->and($this->cartLine->product()->maxAmountPerOrder())->toBe(15);
    $this->cartLine->setQuantity(20);
    expect($this->cartLine->quantity())->toBe(15);

    // https://github.com/laravel/framework/issues/49502
    restore_error_handler();
    restore_exception_handler();
});

it('can export to kerbs', function (): void {
    expect($this->cartLine->toKerbs())->toBeArray();
});
