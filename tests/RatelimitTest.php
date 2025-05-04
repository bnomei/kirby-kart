<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Ratelimit;

beforeEach(function (): void {
    $this->ip = '1.1.1.1';
    Ratelimit::remove($this->ip);
});

afterEach(function (): void {
    Ratelimit::remove($this->ip);
});

it('has a ratelimit helper class', function (): void {
    expect(Ratelimit::class)->toBeString()
        ->and(kart()->option('middlewares.ratelimit.enabled'))->toBeTrue()
        ->and(Ratelimit::check($this->ip))->toBeTrue();

    $limit = intval(kart()->option('middlewares.ratelimit.limit'));
    for ($i = 0; $i < $limit - 2; $i++) {
        Ratelimit::check($this->ip);
    }
    expect(Ratelimit::check($this->ip))->toBeTrue()
        ->and(Ratelimit::check($this->ip))->toBeFalse();

    Ratelimit::flush(true);

    for ($i = 0; $i < $limit - 2; $i++) {
        Ratelimit::check($this->ip);
    }
    expect(Ratelimit::check($this->ip))->toBeTrue()
        ->and(Ratelimit::check($this->ip))->toBeTrue()
        ->and(Ratelimit::check($this->ip))->toBeFalse();

    kart()->setOption('middlewares.ratelimit.enabled', false);
    expect(Ratelimit::check($this->ip))->toBeTrue();
});
