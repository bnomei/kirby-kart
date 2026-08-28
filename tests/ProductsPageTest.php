<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Models\ProductPage;
use Bnomei\Kart\Models\ProductsPage;
use Bnomei\Kart\Provider;
use Kirby\Data\Yaml;
use Kirby\Filesystem\F;

it('has a blueprint from PHP', function (): void {
    expect(Yaml::encode(ProductsPage::phpBlueprint()))->toMatchSnapshot();
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

it('persists provider ownership as a single raw provider field', function (): void {
    /** @var ProductsPage $products */
    $products = page('products');
    $provider = new class(kirby()) extends Provider
    {
        protected string $name = 'shopify';
    };
    $id = uniqid('shopify-', true);
    $title = 'Provider product '.$id;
    $product = [
        'id' => $id,
        'model' => 'product',
        'template' => 'product',
        'content' => [
            'title' => $title,
        ],
    ];
    $page = null;

    try {
        $products->materializeProviderProducts($provider, [$product]);
        $page = $products->childrenAndDrafts()->findBy('raw_provider', 'shopify|'.$id);

        expect($page)->toBeInstanceOf(ProductPage::class)
            ->and($page->raw_provider()->value())->toBe('shopify|'.$id)
            ->and(str_contains(strval(F::read($page->version()->contentFile())), 'Raw-provider: shopify|'.$id))->toBeTrue();
    } finally {
        kirby()->impersonate('kirby', fn () => $page?->delete(true));
    }
});

it('backfills legacy ownership from cache without recreating stale products', function (): void {
    kart()->setOption('products.product.missing', 'draft');

    /** @var ProductsPage $products */
    $products = page('products');
    $provider = new class(kirby()) extends Provider
    {
        protected string $name = 'shopify';
    };
    $id = uniqid('legacy-shopify-', true);
    $product = [
        'id' => $id,
        'model' => 'product',
        'template' => 'product',
        'content' => [
            'title' => 'Legacy provider product '.$id,
        ],
    ];
    $staleId = uniqid('stale-shopify-', true);
    $staleProduct = [
        'id' => $staleId,
        'model' => 'product',
        'template' => 'product',
        'content' => [
            'title' => 'Stale cached product '.$staleId,
        ],
    ];
    $page = null;

    try {
        $products->materializeProviderProducts($provider, [$product]);
        $page = $products->childrenAndDrafts()->findBy('raw_provider', 'shopify|'.$id);
        $page = kirby()->impersonate('kirby', fn () => $page?->update(['raw_provider' => '']));

        $products->syncProviderProducts($provider, [], [], [$product, $staleProduct]);
        $page = $products->childrenAndDrafts()->findBy('raw_provider', 'shopify|'.$id);

        expect($page)->toBeInstanceOf(ProductPage::class)
            ->and($page->isDraft())->toBeTrue()
            ->and($products->childrenAndDrafts()->findBy('raw_provider', 'shopify|'.$staleId))->toBeNull();
    } finally {
        kirby()->impersonate('kirby', fn () => $page?->delete(true));
    }
});

it('backfills legacy ownership outside the license-visible catalog', function (): void {
    /** @var ProductsPage $products */
    $products = page('products');
    $provider = new class(kirby()) extends Provider
    {
        protected string $name = 'stripe';
    };
    $visibleId = uniqid('visible-stripe-', true);
    $hiddenId = uniqid('hidden-stripe-', true);
    $visibleProduct = [
        'id' => $visibleId,
        'model' => 'product',
        'template' => 'product',
        'content' => [
            'title' => 'Visible provider product '.$visibleId,
        ],
    ];
    $hiddenProduct = [
        'id' => $hiddenId,
        'model' => 'product',
        'template' => 'product',
        'content' => [
            'title' => 'Hidden provider product '.$hiddenId,
        ],
    ];
    $visiblePage = null;
    $hiddenPage = null;

    try {
        $products->materializeProviderProducts($provider, [$hiddenProduct]);
        $hiddenPage = $products->childrenAndDrafts()->findBy('raw_provider', 'stripe|'.$hiddenId);
        $hiddenPage = kirby()->impersonate('kirby', fn () => $hiddenPage?->update(['raw_provider' => '']));

        $products->syncProviderProducts(
            $provider,
            [$visibleProduct, $hiddenProduct],
            [$visibleProduct],
        );
        $visiblePage = $products->childrenAndDrafts()->findBy('raw_provider', 'stripe|'.$visibleId);
        $hiddenPage = $products->childrenAndDrafts()->findBy('raw_provider', 'stripe|'.$hiddenId);

        expect($visiblePage)->toBeInstanceOf(ProductPage::class)
            ->and($hiddenPage)->toBeInstanceOf(ProductPage::class)
            ->and($hiddenPage->isListed())->toBeTrue();
    } finally {
        kirby()->impersonate('kirby', function () use ($visiblePage, $hiddenPage): void {
            $visiblePage?->delete(true);
            $hiddenPage?->delete(true);
        });
    }
});

it('drafts removed provider products and keeps them drafted when they reappear', function (): void {
    kart()->setOption('products.product.missing', 'draft');

    /** @var ProductsPage $products */
    $products = page('products');
    $provider = new class(kirby()) extends Provider
    {
        protected string $name = 'shopify';
    };
    $id = uniqid('shopify-', true);
    $title = 'Reconciled product '.$id;
    $product = [
        'id' => $id,
        'model' => 'product',
        'template' => 'product',
        'content' => [
            'title' => $title,
        ],
    ];
    $page = null;

    try {
        $products->materializeProviderProducts($provider, [$product]);
        $products->syncProviderProducts($provider, [], []);
        $page = $products->childrenAndDrafts()->findBy('raw_provider', 'shopify|'.$id);

        expect($page)->toBeInstanceOf(ProductPage::class)
            ->and($page->isDraft())->toBeTrue();

        $products->syncProviderProducts($provider, [$product], [$product]);
        $page = $products->childrenAndDrafts()->findBy('raw_provider', 'shopify|'.$id);

        expect($page)->toBeInstanceOf(ProductPage::class)
            ->and($page->isDraft())->toBeTrue();
    } finally {
        kirby()->impersonate('kirby', fn () => $page?->delete(true));
    }
});

it('keeps removed provider products when configured', function (): void {
    kart()->setOption('products.product.missing', 'keep');

    /** @var ProductsPage $products */
    $products = page('products');
    $provider = new class(kirby()) extends Provider
    {
        protected string $name = 'shopify';
    };
    $id = uniqid('shopify-', true);
    $product = [
        'id' => $id,
        'model' => 'product',
        'template' => 'product',
        'content' => [
            'title' => 'Uncached provider product '.$id,
        ],
    ];
    $page = null;

    try {
        $products->materializeProviderProducts($provider, [$product]);
        $products->syncProviderProducts($provider, [], []);
        $page = $products->childrenAndDrafts()->findBy('raw_provider', 'shopify|'.$id);

        expect($page)->toBeInstanceOf(ProductPage::class)
            ->and($page->isListed())->toBeTrue();
    } finally {
        kirby()->impersonate('kirby', fn () => $page?->delete(true));
    }
});

it('does not republish provider products that were manually drafted', function (): void {
    kart()->setOption('products.product.missing', 'draft');

    /** @var ProductsPage $products */
    $products = page('products');
    $provider = new class(kirby()) extends Provider
    {
        protected string $name = 'shopify';
    };
    $id = uniqid('shopify-', true);
    $title = 'Manually drafted product '.$id;
    $product = [
        'id' => $id,
        'model' => 'product',
        'template' => 'product',
        'content' => [
            'title' => $title,
        ],
    ];
    $page = null;

    try {
        $products->materializeProviderProducts($provider, [$product]);
        $page = $products->childrenAndDrafts()->findBy('raw_provider', 'shopify|'.$id);
        $page = kirby()->impersonate('kirby', fn () => $page?->changeStatus('draft'));

        $products->syncProviderProducts($provider, [], []);
        $products->syncProviderProducts($provider, [$product], [$product]);
        $page = $products->childrenAndDrafts()->findBy('raw_provider', 'shopify|'.$id);

        expect($page)->toBeInstanceOf(ProductPage::class)
            ->and($page->isDraft())->toBeTrue();
    } finally {
        kirby()->impersonate('kirby', fn () => $page?->delete(true));
    }
});

it('deletes removed provider products when configured', function (): void {
    kart()->setOption('products.product.missing', 'delete');

    /** @var ProductsPage $products */
    $products = page('products');
    $provider = new class(kirby()) extends Provider
    {
        protected string $name = 'shopify';
    };
    $id = uniqid('shopify-', true);
    $product = [
        'id' => $id,
        'model' => 'product',
        'template' => 'product',
        'content' => [
            'title' => 'Deleted provider product '.$id,
        ],
    ];
    $page = null;

    try {
        $products->materializeProviderProducts($provider, [$product]);
        $page = $products->childrenAndDrafts()->findBy('raw_provider', 'shopify|'.$id);
        $products->syncProviderProducts($provider, [], []);

        expect($products->childrenAndDrafts()->findBy('raw_provider', 'shopify|'.$id))->toBeNull();
        $page = null;
    } finally {
        kirby()->impersonate('kirby', fn () => $page?->delete(true));
    }
});

it('does not claim a manual page with the same slug', function (): void {
    /** @var ProductsPage $products */
    $products = page('products');
    $provider = new class(kirby()) extends Provider
    {
        protected string $name = 'stripe';
    };
    $id = uniqid('collision-', true);
    $title = 'Manual collision '.$id;
    $page = null;

    try {
        $page = kirby()->impersonate('kirby', fn () => $products->createChild([
            'id' => 'manual-'.$id,
            'model' => 'product',
            'template' => 'product',
            'content' => [
                'title' => $title,
            ],
        ]));

        expect(fn () => $products->materializeProviderProducts($provider, [[
            'id' => 'remote-'.$id,
            'model' => 'product',
            'template' => 'product',
            'content' => [
                'title' => $title,
            ],
        ]]))->toThrow(InvalidArgumentException::class, 'conflicts with existing slug');

        expect($page->raw_provider()->isEmpty())->toBeTrue();
    } finally {
        kirby()->impersonate('kirby', fn () => $page?->delete(true));
    }
});

it('does not transfer a tagged page to another provider', function (): void {
    /** @var ProductsPage $products */
    $products = page('products');
    $stripe = new class(kirby()) extends Provider
    {
        protected string $name = 'stripe';
    };
    $paddle = new class(kirby()) extends Provider
    {
        protected string $name = 'paddle';
    };
    $id = uniqid('ownership-', true);
    $product = [
        'id' => $id,
        'model' => 'product',
        'template' => 'product',
        'content' => [
            'title' => 'Owned product '.$id,
        ],
    ];
    $page = null;

    try {
        $products->materializeProviderProducts($stripe, [$product]);
        $page = $products->childrenAndDrafts()->findBy('raw_provider', 'stripe|'.$id);

        expect(fn () => $products->materializeProviderProducts($paddle, [$product]))
            ->toThrow(InvalidArgumentException::class, 'belongs to stripe|'.$id);

        expect($page)->toBeInstanceOf(ProductPage::class)
            ->and($page->raw_provider()->value())->toBe('stripe|'.$id);
    } finally {
        kirby()->impersonate('kirby', fn () => $page?->delete(true));
    }
});
