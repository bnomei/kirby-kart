<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\ContentPageEnum;
use Kirby\Toolkit\Str;

beforeEach(function (): void {
    $this->p = new Bnomei\Kart\Provider\Kirby(kirby());
});

it('can have a title and name', function (): void {
    expect($this->p->title())->toBe('Kirby Cms')
        ->and($this->p->name())->toBe('kirby_cms');
});

it('can have virtual pages', function (): void {
    expect($this->p->virtual())->toBeFalse();
});

it('can have resolvable options', function (): void {
    expect($this->p->option('doesnotexist', true))->toBeNull();
});

it('can find images in the media pool', function (): void {
    expect($this->p->findImagesFromUrls(['logo.svg']))->toBeEmpty();
});

it('can set and read user data', function (): void {
    $customer = kirby()->impersonate('kirby', fn () => kirby()->users()->create([
        'email' => Str::random(5).'@kart.test',
        'role' => 'customer',
        'password' => Str::random(16),
    ]));
    expect($customer->isCustomer())->toBeTrue();
    kirby()->impersonate($customer);

    $customer = $this->p->setUserData([
        'customer_id' => 123,
        'ts' => time(),
    ], $customer);
    expect($this->p->userData('customer_id'))->toBe(123)
        ->and($this->p->getUserData($customer)['customer_id'])->toBe(123);

    // clean up
    kirby()->impersonate('kirby', function (): void {
        // kirby()->user($customer->email())?->delete();
    });
});

it('can sync the data from the provider', function (): void {
    expect($this->p->sync())->toBeInt()
        ->and($this->p->updatedAt())->toBeString()
        ->and($this->p->updatedAt(ContentPageEnum::PRODUCTS))->toBeString()
        ->and($this->p->updatedAt('products'))->toBeString();
});

it('can have a portal url', function (): void {
    expect($this->p->portal())->toBeNull();
});

it('can get the checkout url', function (): void {
    expect($this->p->checkout())->toBeString();
});

it('can get the canceled url', function (): void {
    expect($this->p->canceled())->toBeString();
});

it('can be completed', function (): void {
    $data = [
        'customer_id' => 123,
    ];
    // missing session_id in kirby will cause fail
    expect($this->p->completed($data))->toBeEmpty();

    // happy path
    $name = Str::random(5);
    $data = [
        'session_id' => '123',
        'email' => $name.'@kart.test',
        'name' => $name,
    ];

    kirby()->session()->set('bnomei.kart.'.$this->p->name().'.session_id', $data['session_id']);

    expect($this->p->completed($data))->not()->toBeEmpty();
});

it('can fetch products', function (): void {
    expect($this->p->products())->toBeArray();
});
