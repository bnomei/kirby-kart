<?php

namespace Bnomei\Kart\Mixins;

use Bnomei\Kart\Helper;
use Kirby\Cms\Collection;

trait CartShortcuts
{
    public function lines(): Collection
    {
        return $this->cart->lines();
    }

    public function count(): int
    {
        return $this->cart->count();
    }

    public function quantity(): int
    {
        return $this->cart->quantity();
    }

    public function sum(): string
    {
        return Helper::formatCurrency($this->cart->sum());
    }

    public function tax(): string
    {
        return Helper::formatCurrency($this->cart->tax());
    }

    public function sumtax(): string
    {
        return Helper::formatCurrency($this->cart->sumtax());
    }
}
