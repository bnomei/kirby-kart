<?php

namespace Bnomei\Kart\Mixins;

trait Captcha
{
    public static function captcha(?string $response = null): ?bool
    {
        $response = $response ?? kart()->option('captcha.current')();
        if (! $response) {
            return null;
        }

        $secret = kart()->option('captcha.get')();
        if (! $secret) {
            return null;
        }

        return $secret === $response;
    }
}
