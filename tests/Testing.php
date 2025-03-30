<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */
class Testing
{
    public static function beforeAll(): void
    {
        kirby()->impersonate('kirby');
        page('products')?->delete(true);
        page('stocks')?->delete(true);
        page('orders')?->delete(true);
        kart()->makeContentPages();
        kart()->tmnt();
    }

    public static function afterAll(): void
    {
        kirby()->impersonate('kirby');
        page('products')?->delete(true);
        page('stocks')?->delete(true);
        page('orders')?->delete(true);
    }
}
