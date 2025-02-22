<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;

class Snipcart extends Provider
{
    protected string $name = ProviderEnum::SNIPCART->value;

    public function checkout(): string
    {
        return '';
    }
}
