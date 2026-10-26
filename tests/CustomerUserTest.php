<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Models\CustomerUser;
use Kirby\Data\Yaml;

it('has a blueprint from PHP', function (): void {
    expect(Yaml::encode(CustomerUser::phpBlueprint()))->toMatchSnapshot();
});

it('clears cart and wishlist when logout destroys an otherwise empty session', function (): void {
    $user = kirby()->impersonate('kirby', fn () => kirby()->users()->create([
        'email' => 'logout-empty-session@example.test',
        'role' => 'customer',
    ]));

    try {
        $user->loginPasswordless();
        $product = page('products')->children()->first();
        kart()->cart()->add($product);
        kart()->wishlist()->add($product);
        expect(kart()->cart()->isEmpty())->toBeFalse()
            ->and(kart()->wishlist()->isEmpty())->toBeFalse();

        // Keep only authentication data so User::logout() destroys the session.
        kirby()->session()->data()->clear();
        kirby()->session()->set('kirby.userId', $user->id());
        $user->logout();

        expect(kirby()->user())->toBeNull()
            ->and(kart()->cart()->isEmpty())->toBeTrue()
            ->and(kart()->wishlist()->isEmpty())->toBeTrue();
    } finally {
        kirby()->impersonate('kirby', fn () => kirby()->user($user->id())?->delete());
        kart()->cart()->clear();
        kart()->wishlist()->clear();
    }
});
