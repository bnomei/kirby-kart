<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;

class Lemonsqueeze extends Provider
{
    protected string $name = ProviderEnum::LEMONSQUEEZE->value;

    public function checkout(): string
    {
        return '/';
    }
}
