<?php
/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart\Mixins;

trait Captcha
{
    public static function hasCaptcha(?string $response = null): ?int
    {
        $current = kart()->option('captcha.current');
        if ($current instanceof \Closure) {
            $current = $current();
        }
        $response = $response ?? $current;
        if (! $response) {
            return null;
        }

        $secret = kart()->option('captcha.get');
        if ($secret instanceof \Closure) {
            $secret = $secret();
        }
        if (! $secret) {
            return null;
        }

        return $secret === $response ? null : 401;
    }
}
