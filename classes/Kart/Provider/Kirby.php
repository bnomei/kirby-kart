<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\Provider;
use ProviderEnum;

class Kirby extends Provider
{
    protected string $name = ProviderEnum::KIRBY->value;

    public function checkout(): string
    {
        return '';
    }
}
