<?php

namespace Bnomei\Kart;

use ProductPage;

class CartLine
{
    private ?\ProductPage $product;

    public function __construct(
        private string $id,
        private int $quantity = 1,
    ) {
        $this->product = page('page://'.$this->id);
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function increment(int $amount = 1): int
    {
        $this->quantity += $amount;

        return $this->quantity;
    }

    public function decrement(int $amount = 1): int
    {
        $this->quantity -= $amount;
        if ($this->quantity <= 0) {
            $this->quantity = 0;
        }

        return $this->quantity;
    }

    /**
     * makes the product unique in the cart line collection
     */
    public function id(): string
    {
        return $this->product()?->uuid()->id() ?? $this->id;
    }

    public function product(): ?ProductPage
    {
        return $this->product;
    }

    public function toArray(): array
    {
        return [
            // 'id' => $this->id(),
            'quantity' => $this->quantity(),
        ];
    }
}
