<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */
it('has a content pages enum', function () {
    expect(\Bnomei\Kart\ContentPageEnum::cases())->toHaveCount(3);
});
