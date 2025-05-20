<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Cart;
use Kirby\Toolkit\Str;

beforeEach(function (): void {
    $this->cart = new Cart;
});

it('has an id', function (): void {
    expect($this->cart->id())->toBe('cart');
});

it('can be initialized with products and quantities', function (): void {
    $this->cart = new Cart(
        'cart',
        page('products')->children()->random(3)->toArray(fn ($p) => ['quantity' => random_int(1, 5)]) + [
            'doesnotexits' => ['quantity' => 1],
        ]
    );

    expect($this->cart->lines()->count())->toBe(3)
        ->and($this->cart->count())->toBe(3)
        ->and($this->cart->quantity())->toBeGreaterThan(0)
        ->and($this->cart->isEmpty())->toBeFalse()
        ->and($this->cart->isNotEmpty())->toBeTrue()
        ->and($this->cart->subtotal())->toBeGreaterThan(0)
        ->and($this->cart->formattedSubtotal())->toBeString()
        ->and($this->cart->hash())->toBeString();

    $f = $this->cart->lines()->first();
    $p = $f->product();
    expect($this->cart->has($f))->toBeTrue()
        ->and($this->cart->has($p))->toBeTrue();

    $this->cart->remove($p, 999);
    expect($this->cart->count())->toBe(2)
        ->and($this->cart->has($p))->toBeFalse();

    $this->cart->add($p->uuid()->id());
    expect($this->cart->has($p))->toBeTrue();
    $this->cart->remove($p, 999);

    $this->cart->add(['id' => $p->id(), 'quantity' => 1]);
    expect($this->cart->has($p))->toBeTrue();
    $this->cart->remove($p, 999);

    $this->cart->delete();
    expect($this->cart->isEmpty())->toBeTrue();
});

it('can check if it can checkout (stock)', function (): void {
    kart()->setOption('stocks.queue', false);

    $products = page('products')->children()->random(3);

    $products->map(function (ProductPage $p) {
        $p = $p->updateStock(1, true);

        return $p;
    });

    expect($products->first()->stock())->toBe(1);

    $this->cart = new Cart(
        'cart',
        $products->toArray(fn ($p) => ['quantity' => 2])
    );

    expect($this->cart->quantity())->toBe(6)
        ->and($this->cart->canCheckout())->toBeFalse()
        ->and($this->cart->allInStock())->toBeFalse();

    $products->map(fn (ProductPage $p) => $p->updateStock(2, true));

    expect($this->cart->canCheckout())->toBeTrue()
        ->and($this->cart->allInStock())->toBeTrue();

    $this->cart->clear();
    expect($this->cart->canCheckout())->toBeFalse();
});

it('can check if it can checkout (maxapo)', function (): void {
    kart()->setOption('stocks.queue', false);

    $products = page('products')->children()->random(4);
    $products->map(function (ProductPage $p) {
        $p = $p->updateStock(20, true);

        return $p;
    });
    expect($products->first()->stock())->toBe(20);
    $other = $products->first();
    $products = $products->remove($other);

    $limit = intval(kart()->option('orders.order.maxapo'));
    $products->map(function (ProductPage $p) {
        $p = $p->updateStock(20, true);

        return $p;
    });
    $this->cart = new Cart(
        'cart',
        $products->toArray(fn ($p) => ['quantity' => $limit + 1])
    );

    // maxapo is NOT enforced by constructor
    // to allow restoring old state
    expect($this->cart->canCheckout())->toBeFalse()
        ->and($this->cart->quantity())->toBe(($limit + 1) * 3)
        ->and($this->cart->canCheckout())->toBeFalse();

    // clean slate
    $this->cart->fix();
    expect($this->cart->canCheckout())->toBeTrue();

    // nor when adding later
    $this->cart->add($other, $limit + 1);
    expect($this->cart->canCheckout())->toBeFalse();

    $this->cart->fix();
    expect($this->cart->canCheckout())->toBeTrue();
});

it('will load items from the current session matching the same ID', function (): void {
    $this->cart = new Cart('some');
    expect($this->cart->lines()->count())->toBe(0);
    $this->cart->add(page('products')->children()->random(1)->first());
    expect($this->cart->lines()->count())->toBe(1);
    $this->cart->save();

    $this->cart = new Cart('some');
    expect($this->cart->lines()->count())->toBe(1);
});

it('can check if it can checkout (hold)', function (): void {
    kart()->setOption('stocks.queue', false);
    kart()->setOption('stocks.hold', 5);

    // holds need a customer
    $customer = kirby()->impersonate('kirby', fn () => kirby()->users()->create([
        'email' => Str::random(5).'@kart.test',
        'role' => 'customer',
        'password' => Str::random(16),
    ]));
    expect($customer->isCustomer())->toBeTrue();
    kirby()->impersonate($customer);

    $products = page('products')->children()->random(2);
    $products->map(fn (ProductPage $p) => $p->updateStock(10, true));

    $cart1 = kart()->cart();
    $products->map(fn ($p) => $cart1->add($p, 5));

    expect($cart1->canCheckout())->toBeTrue()
        ->and($cart1->lines()->count())->toBe(2)
        ->and($cart1->quantity())->toBe(2 * 5);
    $cart1->sessionToken('1');
    $cart1->holdStock();

    $cart2 = new Cart(
        'cart2',
        $products->toArray(fn ($p) => ['quantity' => 10]) // 5 over
    );

    expect($cart1->lines()->count())->toBe(2)
        ->and($cart1->quantity())->toBe(10)
        ->and($cart2->lines()->count())->toBe(2)
        ->and($cart2->quantity())->toBe(20)
        ->and($cart1->canCheckout())->toBeTrue()
        ->and($cart2->canCheckout())->toBeFalse();

    $cart2 = new Cart(
        'cart2',
        $products->toArray(fn ($p) => ['quantity' => 5]) // 0 left
    );
    $cart2->sessionToken('2');
    $cart2->holdStock();

    expect($cart1->canCheckout())->toBeTrue()
        ->and($cart2->canCheckout())->toBeTrue();

    // cleanup
    $cart1->releaseStock();
    $cart1->releaseStock();
    //    kirby()->impersonate('kirby', function () use ($customer) {
    //        kirby()->user($customer->id())?->delete();
    //    });
});

it('can merge with the cart of the user', function (): void {
    $customer = kirby()->impersonate('kirby', fn () => kirby()->users()->create([
        'email' => Str::random(5).'@kart.test',
        'role' => 'customer',
        'password' => Str::random(16),
    ]));
    expect($customer->isCustomer())->toBeTrue();
    kirby()->impersonate($customer);

    $products = page('products')->children()->random(2);
    $p1 = $products->first();
    $p2 = $products->last();

    $cart1 = $customer->cart();
    $cart1->clear();
    $cart1->add($p1, 2);
    $cart1->save(); // write to field
    // refresh after save
    $customer = kirby()->user($customer->id());

    expect($cart1->quantity())->toBe(2)
        ->and($customer->kart_cart()->yaml()[$p1->uuid()->id()]['quantity'])->toBe(2);

    $cart2 = new Cart(
        'cart', // same id but virtual!
        [
            $p2->id() => ['quantity' => 3],
        ]
    );
    // no save() HERE or inside the constructor

    expect($cart2->quantity())->toBe(3)
        ->and($customer->kart_cart()->yaml()[$p1->uuid()->id()]['quantity'])->toBe(2);

    $success = $cart2->merge($customer); // cart id will match field
    $cart2->save();

    expect($success)->toBeTrue()
        ->and($cart1->quantity())->toBe(2)
        ->and($cart2->quantity())->toBe(5)
        ->and($customer->cart()->quantity())->toBe(2); // still old cart1

    $cart3 = new Cart; // will load from session
    // $cart3->save();
    expect($cart3->quantity())->toBe(5);

    // NOTE: to update the cart in the user would
    // need a setter on kart() but since the merge
    // is intended to be INTO the current users virtual
    // cart with the items stored in content file
    // that does not need to be tested here.

    // clean up
    kirby()->impersonate('kirby', function () use ($customer): void {
        kirby()->user($customer->id())?->delete();
    });
});

it('can complete a cart', function (): void {
    $this->cart = new Cart(
        'cart',
        page('products')->children()->random(3)->toArray(fn ($p) => ['quantity' => random_int(1, 5)])
    );

    expect($this->cart->complete())->toBeString();
});

it('can export to kerbs', function (): void {
    $this->cart = new Cart(
        'cart',
    );

    expect($this->cart->toKerbs())->toBeArray();
});
