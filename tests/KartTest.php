<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Cart;
use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Kart;
use Bnomei\Kart\Provider;
use Bnomei\Kart\Queue;
use Bnomei\Kart\Urls;
use Bnomei\Kart\Wishlist;
use Kirby\Cms\App;
use Kirby\Cms\Pages;
use Kirby\Toolkit\Str;

beforeAll(function () {
    Testing::beforeAll();
});

afterAll(function () {
    // Testing::afterAll();
});

it('has a singleton', function () {
    expect(Kart::singleton())->toBeInstanceOf(Kart::class);
});

it('has an url collection', function () {
    expect(kart()->urls())->toBeInstanceOf(Urls::class);
});

it('has various shorthands for urls', function () {
    expect(kart()->checkout())->toBeString()
        ->and(kart()->login('x@kart.test'))->toBeString()
        ->and(kart()->logout())->toBeString()
        ->and(kart()->sync())->toBeString();
});

it('has a queue', function () {
    expect(kart()->queue())->toBeInstanceOf(Queue::class);
});

it('has a kirby ref', function () {
    expect(kart()->kirby())->toBeInstanceOf(App::class);
});

it('has a cart and a wishlist', function () {
    expect(kart()->cart())->toBeInstanceOf(Cart::class)
        ->and(kart()->wishlist())->toBeInstanceOf(Wishlist::class);
});

it('has the current provider', function () {
    expect(kart()->provider())->toBeInstanceOf(Provider::class);
});

it('can flush caches', function () {
    expect(kart()->flush())->toBeTrue();
});

it('can encrypt and decrypt', function () {
    kart()->flush('crypto');
    // kart()->setOption('crypto.password', 'kart');

    $data = [
        'foo' => 'bar',
    ];
    $e = Kart::encrypt($data, 'kart', true);

    expect($e)->toBeString()
        // ->and($e)->toMatchSnapshot()
        ->and(Kart::decrypt($e, 'kart', true))->toBe($data);
});

it('can hash', function () {
    expect(Kart::hash('hello'))->toBe('9555e8555c62dcfd');
});

it('can zero pad', function () {
    expect(Kart::zeroPad('123', 5))->toBe('00123');
});

it('can format a number', function () {
    expect(Kart::formatNumber(123.9))->toBe('123.9');
});

it('can format a currency value', function () {
    expect(Kart::formatCurrency(123.9))->toBe('€123.90')
        ->and(kart()->currency())->toBe('EUR');
});

it('can create non ambiguous uuids', function () {
    expect(Kart::nonAmbiguousUuid(3))->toHaveLength(3)
        ->not()->toContain('o', 'O', 'l', 'L', 'I', 'i', 'B', 'S', 's');
});

it('can sanitize data', function () {
    expect(Kart::sanitize('<?= $foo'))->toBe('')
        ->and(Kart::sanitize([
            '<b>foo' => '<?php $bar',
            'bar' => ['baz'],
        ]))->toBe([
            '<b>foo' => '',
            'bar' => ['baz'],
        ]);
});

it('can set and get a message with channels', function () {
    kart()->message('foo');
    expect(kart()->message())->toBe('foo')
        ->and(kart()->message(channel: 'default'))->toBeNull();

    kart()->message('hello', 'world');
    expect(kart()->message(channel: 'world'))->toBe('hello');
});

it('can create or update a customer', function () {
    $email = Str::lower(Str::random(5).'@kart.test');
    $c = kirby()->user($email);
    expect($c)->toBeNull();

    /** @var CustomerUser $c */
    $c = kart()->createOrUpdateCustomer([
        'customer' => [
            'email' => $email,
            'id' => 'cu_1234',
        ],
    ]);
    expect($c)->not()->toBeNull()
        ->and($c->email())->toBe($email)
        ->and($c->isCustomer())->toBeTrue()
        ->and(kart()->provider()->getUserData($c))->toBe([
            'customerId' => 'cu_1234',
        ]);
});

it('can get the kart root collections', function () {
    expect(kart()->orders())->toBeInstanceOf(Pages::class)
        ->and(kart()->products())->toBeInstanceOf(Pages::class)
        ->and(kart()->stocks())->toBeInstanceOf(Pages::class);
});

it('can get the kart root pages', function () {
    expect(kart()->page(ContentPageEnum::PRODUCTS))->toBeInstanceOf(ProductsPage::class)
        ->and(kart()->page('orders'))->toBeInstanceOf(OrdersPage::class);
});

it('can find products with no stock', function () {
    kart()->setOption('stocks.queue', false);

    /** @var ProductPage $p */
    $p = kart()->products()->first();
    $p = $p->updateStock(0, true);

    expect($p->stock())->toBe(0)
        ->and(kart()->productsWithoutStocks()->count())->toBe(0)
        ->and(kart()->products()->filter(fn (ProductPage $page) => $page->stock() === 0)->count())->toBeGreaterThan(0);
});

it('can find related products', function () {
    /** @var ProductPage $p */
    $p = kart()->products()->first();
    $r = kart()->productsRelated($p);

    expect($r)->toBeInstanceOf(Pages::class)
        ->and($r->count())->toBe(9)
        ->and($p->tags()->split())->toHaveCount(1)
        ->and($p->categories()->split())->toHaveCount(1)
        ->and(kart()->tags()->count())->toBe(19)
        ->and(kart()->categories()->count())->toBe(4);
});

it('can find orders', function () {
    $p = kart()->products()->first();
    $customer = kirby()->impersonate('kirby', function () {
        return kirby()->users()->create([
            'email' => Str::random(5).'@kart.test',
            'role' => 'customer',
        ]);
    });
    expect($customer->isCustomer())->toBeTrue();
    kirby()->impersonate($customer);

    /** @var OrdersPage $orders */
    $orders = kart()->page('orders');
    /** @var OrderPage $o */
    $o = $orders->createOrder([
        'paymentComplete' => true,
        'items' => [
            [
                'key' => [$p->uuid()->toString()],
                'quantity' => 1,
            ],
        ],
    ], $customer);

    expect($o->hasProduct($p))->toBeTrue()
        ->and(kart()->ordersWithProduct($p)->count())->toBeGreaterThan(0)
        ->and(kart()->ordersWithCustomer($customer)->count())->toBe(1)
        ->and(kart()->ordersWithInvoiceNumber($o->invnumber()->toInt())->id())->toBe($o->id());
});
