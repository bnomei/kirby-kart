<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Router;

it('sanitizes redirect targets to internal urls only', function (): void {
    $sameHostAbsolute = site()->url().'/cart?ok=1#done';

    expect(Router::safeRedirect('/cart'))->toBe('/cart')
        ->and(Router::safeRedirect('?ok=1'))->toBe('?ok=1')
        ->and(Router::safeRedirect('#details'))->toBe('#details')
        ->and(Router::safeRedirect($sameHostAbsolute))->toBe('/cart?ok=1#done')
        ->and(Router::safeRedirect('https://evil.example/phish'))->toBe('/')
        ->and(Router::safeRedirect('//evil.example/phish'))->toBe('/');
});
