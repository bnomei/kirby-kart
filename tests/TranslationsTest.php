<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */
it('registers Swedish translations for the Kirby Panel locale code', function (): void {
    $translation = kirby()->translation('sv_SE')->toArray()['data'] ?? [];

    expect($translation)
        ->toHaveKey('bnomei.kart.cart')
        ->and($translation['bnomei.kart.cart'])->toBe('Varukorg');
});
