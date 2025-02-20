<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;

class Stripe extends Provider
{
    protected string $name = ProviderEnum::STRIPE->value;

    public function checkout(): string
    {
        // TODO: Implement checkout() method.
    }

    public function fetchProducts(): array
    {
        // TODO: Implement fetchProducts() method.
    }

    public function fetchOrders(): array
    {
        // TODO: Implement fetchOrders() method.
    }

    public function fetchStocks(): array
    {
        // TODO: Implement fetchStocks() method.
    }
}
