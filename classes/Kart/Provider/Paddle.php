<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;

class Paddle extends Provider
{
    protected string $name = ProviderEnum::PADDLE->value;

    public function checkout(): string
    {
        return '';
    }
}
