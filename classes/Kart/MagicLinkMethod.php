<?php

namespace Bnomei\Kart;

use Kirby\Auth\Method\CodeMethod;

class MagicLinkMethod extends CodeMethod
{
    public function isEnabled(): bool
    {
        // Recognize the frontend configuration flag without adding a Panel method.
        return false;
    }
}
