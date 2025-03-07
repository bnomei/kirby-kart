<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;

class Invoiceninja extends Provider
{
    protected string $name = ProviderEnum::INVOICE_NINJA->value;

    public function checkout(): string
    {
        return '/';
    }
}
