<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;

class Stripe extends Provider
{
    protected string $name = ProviderEnum::STRIPE->value;
}
