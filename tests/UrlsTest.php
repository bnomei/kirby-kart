<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Urls;

it('has a helper object to get urls from the router builder functions', function () {
    expect(Urls::class)->toBeString();

    $u = new Urls;
    expect($u->cart())->toBeString()
        ->and($u->login_magic())->toBe($u->magiclink());
});
