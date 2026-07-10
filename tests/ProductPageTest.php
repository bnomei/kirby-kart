<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Models\ProductPage;
use Bnomei\Kart\ProductStorage;
use Kirby\Data\Yaml;

it('has a blueprint from PHP', function (): void {
    expect(Yaml::encode(ProductPage::phpBlueprint()))->toMatchSnapshot();
});

it('has a custom storage to allow merging with the virtual pages', function (): void {
    /** @var ProductPage $p */
    $p = page('products')->children()->first();
    expect($p->storage())->toBeInstanceOf(ProductStorage::class);
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

it('builds valid product JSON-LD with a complete offer', function (): void {
    /** @var ProductPage $p */
    $p = page('products')->children()->first();
    $data = $p->productJsonLd();

    expect($data['@type'])->toBe('Product')
        ->and($data)->not->toHaveKeys(['description', 'image'])
        ->and($data['offers']['@type'])->toBe('Offer')
        ->and($data['offers']['price'])->toBe(15.0)
        ->and($data['offers']['priceCurrency'])->toBe('EUR')
        ->and($data['offers']['availability'])->toBeIn([
            'https://schema.org/InStock',
            'https://schema.org/OutOfStock',
        ])
        ->and($data['offers']['url'])->toBe($p->url());

    $html = snippet('kart/product-json-ld', ['product' => $p], true);
    preg_match(
        '/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s',
        strval($html),
        $matches,
    );

    expect(json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR))
        ->toBe($data);
});

it('builds ProductGroup JSON-LD for multi-dimensional variants', function (): void {
    $p = new ProductPage([
        'slug' => 'special-product',
        'parent' => page('products'),
        'template' => 'product',
        'model' => 'product',
        'content' => [
            'title' => 'Special "Product"',
            'uuid' => 'special-product',
            'description' => "First line\n\nSecond line </script>",
            'price' => 10,
            'variants' => Yaml::encode([
                [
                    'variant' => 'color:red, size:large',
                    'price' => 12.5,
                ],
                [
                    'variant' => 'license.desktop, material:wool',
                    'price' => 20,
                ],
            ]),
        ],
    ]);

    $data = $p->productJsonLd();

    expect($data['@type'])->toBe('ProductGroup')
        ->and($data['variesBy'])->toBe([
            'https://schema.org/color',
            'https://schema.org/material',
            'https://schema.org/size',
        ])
        ->and($data['hasVariant'])->toHaveCount(2)
        ->and($data['hasVariant'][0]['color'])->toBe('red')
        ->and($data['hasVariant'][0]['size'])->toBe('large')
        ->and($data['hasVariant'][0]['offers']['price'])->toBe(12.5)
        ->and($data['hasVariant'][1]['name'])->toBe('Special "Product" – desktop, wool')
        ->and($data['hasVariant'][1]['material'])->toBe('wool')
        ->and($data['hasVariant'][1]['additionalProperty'][0])->toBe([
            '@type' => 'PropertyValue',
            'name' => 'license',
            'value' => 'desktop',
        ])
        ->and($data['hasVariant'][1]['offers']['price'])->toBe(20.0)
        ->and($data['hasVariant'][1]['offers']['url'])
        ->toEndWith('?license=desktop&material=wool');

    $html = snippet('kart/product-json-ld', ['product' => $p], true);

    expect(substr_count(strval($html), '</script>'))->toBe(1);
});
