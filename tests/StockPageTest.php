<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Models\StockPage;
use Kirby\Data\Yaml;

it('has a blueprint from PHP', function (): void {
    expect(Yaml::encode(StockPage::phpBlueprint()))->toMatchSnapshot();
});

it('can pad the stock with zeros', function (): void {
    /** @var StockPage $s */
    $s = page('stocks')->children()->first();
    expect($s->stockPad(3))->toHaveLength(3);
});
