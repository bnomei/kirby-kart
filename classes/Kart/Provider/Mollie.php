<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;

class Mollie extends Provider
{
    protected string $name = ProviderEnum::MOLLIE->value;

    public function checkout(): string
    {
        return '';
    }
}
