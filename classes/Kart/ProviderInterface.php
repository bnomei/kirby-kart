<?php

namespace Bnomei\Kart;

interface ProviderInterface
{
    public function checkout(): string;

    public function complete(): array;

    public function fetchProducts(): array;

    public function fetchOrders(): array;

    public function fetchStocks(): array;
}
