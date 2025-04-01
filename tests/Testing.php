<?php

use Kirby\Filesystem\Dir;

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
        kirby()->impersonate('kirby', function (): void {
            //            page('products')?->children()->map(function ($p) {
            //                $p->delete(true);
            //
            //                return $p;
            //            });
            //            page('stocks')?->children()->map(function ($p) {
            //                $p->delete(true);
            //
            //                return $p;
            //            });
            //            page('orders')?->children()->map(function ($p) {
            //                $p->delete(true);
            //
            //                return $p;
            //            });
            kart()->makeContentPages();
            kart()->tmnt();
            kart()->cart()->clear();
            kart()->cart()->save();
            Dir::remove(kirby()->cache('bnomei.kart')->root());
        });
    }

    public static function afterAll(): void
    {
        kirby()->impersonate('kirby', function (): void {
            page('products')?->children()->map(function ($p) {
                $p->delete(true);

                return $p;
            });
            page('stocks')?->children()->map(function ($p) {
                $p->delete(true);

                return $p;
            });
            page('orders')?->children()->map(function ($p) {
                $p->delete(true);

                return $p;
            });

            kart()->cart()->clear();
            kart()->cart()->save();
            Dir::remove(kirby()->cache('bnomei.kart')->root());
        });
    }
}
