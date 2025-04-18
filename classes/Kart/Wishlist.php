<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Kirby\Toolkit\A;

class Wishlist extends Cart
{
    public function __construct(string $id = 'wishlist', array $items = [])
    {
        parent::__construct($id, $items);
    }

    public function toKerbs(): array
    {
        return A::get(parent::toKerbs(), [
            'count',
            'hash',
            'id',
            'lines',
            'url',
        ]);
    }
}
