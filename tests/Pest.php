<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

/**
 * Ensure provider tests don't leak stub cart state into other provider tests.
 * Some provider tests inject a stub cart that returns non-CartLine objects.
 */
function resetProviderTestCart(): void
{
    if (function_exists('kirby')) {
        try {
            kirby()->session()->set('cart', []);
        } catch (Throwable) {
            // ignore session issues in edge cases
        }
    }

    if (function_exists('kart')) {
        (function (): void {
            $this->cart = null;
        })->call(kart());
    }
}

uses()
    ->beforeEach(fn () => resetProviderTestCart())
    ->afterEach(fn () => resetProviderTestCart())
    ->in('Providers');
