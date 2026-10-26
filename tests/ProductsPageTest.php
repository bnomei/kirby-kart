<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Models\ProductsPage;
use Kirby\Cms\Page;
use Kirby\Data\Yaml;

it('has a blueprint from PHP', function (): void {
    expect(Yaml::encode(ProductsPage::phpBlueprint()))->toMatchSnapshot();
});

it('uses gallery order for product card images and allows an empty gallery', function (): void {
    $query = ProductsPage::phpBlueprint()['tabs']['overview']['sections']['products']['image']['query'];
    $product = new Page([
        'slug' => 'gallery-order',
        'files' => [
            ['filename' => 'first.jpg'],
            ['filename' => 'second.jpg'],
        ],
        'content' => ['gallery' => Yaml::encode(['second.jpg', 'first.jpg'])],
    ]);

    expect($product->query($query)?->filename())->toBe('second.jpg')
        ->and((new Page(['slug' => 'no-gallery']))->query($query))->toBeNull();
});

it('has categories and tags shortcuts', function (): void {
    /** @var ProductsPage $p */
    $p = page('products');

    expect($p->categories()->split())->toHaveCount(4)
        ->and($p->tags()->split())->toHaveCount(19);
});

it('has shortcut for products that are out of stock', function (): void {
    /** @var ProductsPage $p */
    $p = page('products');

    expect($p->outOfStock()->count())->toBe(1);
});
