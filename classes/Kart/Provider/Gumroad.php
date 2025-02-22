<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;

class Gumroad extends Provider
{
    protected string $name = ProviderEnum::GUMROAD->value;

    public function checkout(): string
    {
        return '';
    }
}
