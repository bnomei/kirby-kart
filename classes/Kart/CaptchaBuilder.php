<?php

namespace Bnomei\Kart;

use SimpleCaptcha\Builder;

class CaptchaBuilder extends Builder
{
    protected static string $charset = 'abcdefghjkmnpqrtuvwxyzACDEFGHJKLMNOPQRTUVWXYZ23467';

    public static function buildPhrase(int $length = 5, ?string $charset = null): string
    {
        // Build random string
        $phrase = '';

        for ($i = 0; $i < $length; $i++) {
            $phrase .= static::randomCharacter($charset);
        }

        return $phrase;
    }

    public static function randomCharacter(?string $charset = null): string
    {
        if (is_null($charset)) {
            $charset = static::$charset;
        }

        return parent::randomCharacter($charset);
    }
}
