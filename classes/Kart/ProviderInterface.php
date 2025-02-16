<?php

namespace Bnomei\Kart;

interface ProviderInterface
{
    /**
     * The Checkout URL for redirecting
     */
    public function checkout(): string;
}
