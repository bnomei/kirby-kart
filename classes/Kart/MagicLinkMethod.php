<?php

namespace Bnomei\Kart;

use Kirby\Auth\Auth;
use Kirby\Auth\Method\CodeMethod;

class MagicLinkMethod extends CodeMethod
{
    public static function type(): string
    {
        return 'kart-magic-link';
    }

    public static function isEnabled(Auth $auth, array $options = []): bool
    {
        // Recognize the frontend configuration flag without adding a Panel method.
        return false;
    }
}
