<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Kirby\Cache\FileCache;
use Kirby\Filesystem\Dir;
use Kirby\Http\Visitor;

class Ratelimit
{
    public static function check(Visitor|string|null $ip): bool
    {
        $kirby = kirby();
        if (! kart()->option('middlewares.ratelimit.enabled')) {
            return true;
        }

        if (! $kirby->environment()->isLocal() && $kirby->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
            return true;
        }

        $cacheDurationInMinutes = intval(kart()->option('middlewares.ratelimit.duration', 1));

        $ip ??= strval($kirby->visitor()->ip());
        $limit = intval(kart()->option('middlewares.ratelimit.limit'));
        $key = sha1(__DIR__.$ip.date('Ymd'));
        $count = $kirby->cache('bnomei.kart.ratelimit')->get(
            $key,
            0
        );

        $count++;

        if ($count > $limit) {
            kirby()->trigger('kart.ratelimit.hit', [
                'ip' => $ip,
                'key' => $key,
                'count' => $count,
                'limit' => $limit,
            ]);

            return false;
        }
        // write after check to avoid unnecessary writes if the rate limit was hit
        $kirby->cache('bnomei.kart.ratelimit')->set($key, $count, $cacheDurationInMinutes); // store for N minutes

        return true;
    }

    public static function remove(?string $ip = null): void
    {
        $kirby = kirby();
        $ip ??= strval($kirby->visitor()->ip());
        $key = sha1(__DIR__.$ip.date('Ymd'));

        $kirby->cache('bnomei.kart.ratelimit')->remove($key);
    }

    public static function flush(): void
    {
        $kirby = kirby();
        /** @var FileCache $cache */
        $cache = $kirby->cache('bnomei.kart.ratelimit');
        $dir = $cache->root();
        if (! is_dir($dir)) {
            return;
        }

        $cacheDurationInMinutes = intval(kart()->option('middlewares.ratelimit.duration', 1));

        foreach (Dir::files($dir, null, true) as $file) {
            $time = filemtime($file);
            if ($time && $time < time() - ($cacheDurationInMinutes * 60)) {
                @unlink($file);
            }
        }
    }
}
