<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Ratelimit;

beforeEach(function () {
    $this->ip = '1.1.1.1';
    Ratelimit::flush($this->ip);
});

afterEach(function () {
    Ratelimit::flush($this->ip);
});

it('has a ratelimit helper class', function () {
    expect(Ratelimit::class)->toBeString()
        ->and(kart()->option('middlewares.ratelimit.enabled'))->toBeTrue()
        ->and(Ratelimit::check($this->ip))->toBeTrue();

    $limit = intval(kart()->option('middlewares.ratelimit.limit'));
    for ($i = 0; $i < $limit - 2; $i++) {
        Ratelimit::check($this->ip);
    }
    expect(Ratelimit::check($this->ip))->toBeTrue()
        ->and(Ratelimit::check($this->ip))->toBeFalse();

    kart()->setOption('middlewares.ratelimit.enabled', false);
    expect(Ratelimit::check($this->ip))->toBeTrue();
});
