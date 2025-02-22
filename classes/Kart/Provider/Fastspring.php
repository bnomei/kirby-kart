<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;

class Fastspring extends Provider
{
    protected string $name = ProviderEnum::FASTSPRING->value;

    public function checkout(): string
    {
        return '';
    }
}
