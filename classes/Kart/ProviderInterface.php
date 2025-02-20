<?php

namespace Bnomei\Kart;

interface ProviderInterface
{
    /**
     * The Checkout URL for redirecting
     */
    public function checkout(): string;

    public function fetchProducts(): array;

    public function fetchOrders(): array;

    public function fetchStocks(): array;
}
