<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */
use Bnomei\Kart\ProviderEnum;

it('has provider enum', function (): void {
    expect(ProviderEnum::class)->toBeString()
        ->and(ProviderEnum::cases())->toHaveCount(14);
});
