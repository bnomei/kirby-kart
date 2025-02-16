<?php

namespace Bnomei\Kart;

class Product
{
    private int $amount;

    public function __construct(public string $id)
    {
        $this->amount = 1;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function increment(): int
    {
        $this->amount++;

        return $this->amount;
    }

    public function decrement(): int
    {
        $this->amount--;

        return $this->amount;
    }

    /**
     * makes the product unique in the cart line collection
     */
    public function id(): string
    {
        return $this->id;
    }

    public function price(): float
    {
        return 0;
    }

    public function tax(): float
    {
        return 0;
    }

    public function add(): string
    {
        return Router::cart_add($this);
    }

    public function remove(): string
    {
        return Router::cart_remove($this);
    }

    public function wish(): string
    {
        return Router::wishlist_add($this);
    }

    public function forget(): string
    {
        return Router::wishlist_remove($this);
    }
}
