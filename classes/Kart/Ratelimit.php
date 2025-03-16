<?php

namespace Bnomei\Kart;

use Kirby\Http\Visitor;

class Ratelimit
{
    public static function check(Visitor|string|null $ip): bool
    {
        $kirby = kirby();
        if (! kart()->option('ratelimit.enabled')) {
            return true;
        }

        if (! $kirby->environment()->isLocal() && $kirby->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
            return true;
        }

        $cacheDurationInMinutes = 60;
        $rateLimitResetIntervalInSeconds = 60;

        $ip = $ip ?? strval($kirby->visitor()->ip());
        $limit = intval(kart()->option('ratelimit.limit')); // 12 per minute within 1 hour
        $key = sha1(__DIR__.$ip.date('Ymd'));
        [$expireAt, $count] = $kirby->cache('bnomei.kart.ratelimit')->get(
            $key,
            [time() + $rateLimitResetIntervalInSeconds * $cacheDurationInMinutes, 0] // defaults to expire with caching duration if there is no cache, which will be once every hour
        );

        // reset if set expire time has passed
        if ($expireAt < time()) {
            $expireAt = time() + $rateLimitResetIntervalInSeconds;
            $count = 0;
        }

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
        // write after check to avoid unnecessary writes if ratelimit was hit
        $kirby->cache('bnomei.kart.ratelimit')->set($key, [$expireAt, $count], $cacheDurationInMinutes); // store for 1 hour

        return true;
    }
}
