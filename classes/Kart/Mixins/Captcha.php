<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart\Mixins;

use Closure;

trait Captcha
{
    public static function hasCaptcha(?string $response = null): ?int
    {
        if (kart()->option('captcha.enabled') === false) {
            return null;
        }

        $current = kart()->option('captcha.current');
        if ($current instanceof Closure) {
            $current = $current();
        }
        $response ??= $current;

        $secret = kart()->option('captcha.get');
        if ($secret instanceof Closure) {
            $secret = $secret();
        }

        return $secret === $response ? null : 401;
    }
}
