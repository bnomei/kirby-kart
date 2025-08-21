<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Models\OrderPage;
use Bnomei\Kart\Models\OrdersPage;
use Kirby\Data\Yaml;

it('has a blueprint from PHP', function (): void {
    expect(Yaml::encode(OrdersPage::phpBlueprint()))->toMatchSnapshot();
});

it('can disable order creation', function (): void {
    kart()->setOption('orders.enabled', false);

    /** @var OrdersPage $orders */
    $orders = kart()->page('orders');

    /** @var OrderPage $o */
    $o = $orders->createOrder([]);
    expect($o)->toBeNull();
});
