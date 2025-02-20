<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\Provider;
use ProviderEnum;

class Mollie extends Provider
{
    protected string $name = ProviderEnum::MOLLIE->value;
}
