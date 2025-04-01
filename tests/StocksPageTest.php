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
    expect(Yaml::encode(StocksPage::phpBlueprint()))->toMatchSnapshot();
});

it('can update stocks with and without queue', function (): void {
    kart()->setOption('stocks.queue', false);
    /** @var ProductPage $p */
    $p = page('products')->children()->first();

    $p->updateStock(10, set: true);
    expect($p->stock())->toBe(10)
        ->and($p->inStock());

    kart()->setOption('stocks.queue', true);
    $p->updateStock(15, set: true);
    expect($p->stock())->toBe(10);
    kart()->queue()->process();
    expect($p->stock())->toBe(15);
});

it('can find the stock page for a product', function (): void {
    /** @var StocksPage $s */
    $s = page('stocks');
    /** @var ProductPage $p */
    $p = page('products')->children()->first();
    /** @var StockPage $stock */
    $stock = $s->stockPages($p)->first();
    expect($stock->page()->toPage()->id())->toBe($p->id());
});

it('can batch update stocks (like from an order)', function (): void {
    kart()->setOption('stocks.queue', false);

    /** @var StocksPage $s */
    $s = page('stocks');

    $p = page('products')->children()->first();
    $l = page('products')->children()->nth(2);
    $count = $s->updateStocks([
        'items' => [
            ['key' => [$p->uuid()->toString()], 'quantity' => 2],
            ['key' => [$l->uuid()->toString()], 'quantity' => 3],
        ],
    ], -1);

    // count will only be not null if not using queues
    expect($count)->toBe(2); // count, not sum of changes
});
