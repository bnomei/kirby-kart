<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;

class Payone extends Provider
{
    protected string $name = ProviderEnum::PAYONE->value;

    public function checkout(): string
    {
        return '/';
    }
}
