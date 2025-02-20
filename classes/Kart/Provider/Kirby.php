<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;

class Kirby extends Provider
{
    protected string $name = ProviderEnum::KIRBY->value;

    public function checkout(): string
    {
        return kirby()->site()->url();
    }

    public function fetchProducts(): array
    {
        return [];
    }

    public function fetchOrders(): array
    {
        return [];
    }

    public function fetchStocks(): array
    {
        return [];
    }

    public function updatedAt(ContentPageEnum|string|null $sync): string
    {
        return t('kart.now', 'Now');
    }
}
