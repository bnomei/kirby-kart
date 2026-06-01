<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Router;

it('rejects redirect targets with backslashes before treating them as internal', function (): void {
    expect(Router::safeRedirect('/\\evil.example/phish'))->toBe('/')
        ->and(Router::safeRedirect('/%5Cevil.example/phish'))->toBe('/')
        ->and(Router::safeRedirect('/%5cevil.example/phish'))->toBe('/')
        ->and(Router::safeRedirect('/cart'))->toBe('/cart');
});
