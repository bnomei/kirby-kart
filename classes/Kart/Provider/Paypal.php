<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;

class Paypal extends Provider
{
    protected string $name = ProviderEnum::PAYPAL->value;

    public function checkout(): string
    {
        return '/';
    }
}
