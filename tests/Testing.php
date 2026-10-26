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
            foreach (['products', 'stocks', 'orders'] as $id) {
                foreach (page($id)?->children()->values() ?? [] as $child) {
                    $child->delete(true);
                }
            }
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
            foreach (['products', 'stocks', 'orders'] as $id) {
                foreach (page($id)?->children()->values() ?? [] as $child) {
                    $child->delete(true);
                }
            }

            kart()->cart()->clear();
            kart()->cart()->save();
            Dir::remove(kirby()->cache('bnomei.kart')->root());
        });
    }
}
