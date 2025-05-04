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
    expect(Yaml::encode(ProductPage::phpBlueprint()))->toMatchSnapshot();
});

it('has a custom storage to allow merging with the virtual pages', function (): void {
    /** @var ProductPage $p */
    $p = page('products')->children()->first();
    expect($p->storage())->toBeInstanceOf(\Bnomei\Kart\ProductStorage::class);
});

it('has a link to the url of its stock in the panel', function (): void {
    /** @var ProductPage $p */
    $p = page('products')->children()->first();
    expect($p->stockUrl())->toBeString();
});

it('has a price', function (): void {
    /** @var ProductPage $p */
    $p = page('products')->children()->first();

    expect($p->price()->toInt())->toBe(15)
        ->and($p->rrprice()->value())->toBe('')
        ->and($p->rrpp())->toBe(0.0)
        ->and($p->formattedPrice())->toBe('€15.00');
});

it('has shorthands for the API urls', function (): void {
    /** @var ProductPage $p */
    $p = page('products')->children()->first();

    expect($p->addToCart())->toBeString()
        ->and($p->add())->toBeString()
        ->and($p->buy())->toBeString()
        ->and($p->buyNow())->toBeString()
        ->and($p->removeFromCart())->toBeString()
        ->and($p->remove())->toBeString()
        ->and($p->moveFromCartToWishlist())->toBeString()
        ->and($p->later())->toBeString()
        ->and($p->addToWishlist())->toBeString()
        ->and($p->wish())->toBeString()
        ->and($p->moveFromWishlistToCart())->toBeString()
        ->and($p->now())->toBeString()
        ->and($p->removeFromWishlist())->toBeString()
        ->and($p->forget())->toBeString()
        ->and($p->gumroadUrl())->toBeNull()
        ->and($p->lemonsqueezyUrl())->toBeNull()
        ->and($p->setAmountInCart())->toBeString();
});

it('can get the first image', function (): void {
    /** @var ProductPage $p */
    $p = page('products')->children()->first();

    expect($p->firstGalleryImage())->toBeNull()
        ->and($p->firstGalleryImageUrl())->toBeNull();
});

it('can be owned by a user', function (): void {
    /** @var ProductPage $p */
    $p = page('products')->children()->first();

    expect($p->ownedByUser())->toBeFalse();
});

it('can export to kerbs', function (): void {
    /** @var ProductPage $p */
    $p = page('products')->children()->first();

    expect($p->toKerbs(true))->toBeArray()
        ->and($p->toKerbs(false))->toBeArray();
});

it('can handle variants data even if it has none', function (): void {
    /** @var ProductPage $p */
    $p = page('products')->children()->first();

    expect($p->priceWithVariant())->toBe(15.0)
        ->and($p->hasVariant())->toBeFalse()
        ->and($p->variantData())->toBeArray()
        ->and($p->variantGroups())->toBeArray()
        ->and($p->variantFromRequestData([]))->toBeNull();
});
