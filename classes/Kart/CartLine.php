<?php

namespace Bnomei\Kart;

use ProductPage;

class CartLine
{
    private ?ProductPage $product;

    public function __construct(
        private string $id, // need to be named `id` for Collections to use it as key
        private int $quantity = 1,
    ) {
        // id is expected to be the uuid in all cases!
        $this->product = page('page://'.$this->id); // @phpstan-ignore-line
    }

    public function increment(int $amount = 1): int
    {
        $new = $this->quantity + $amount;
        $max = $this->product()?->maxAmountPerOrder();

        if ($max && $new > $max) {
            $new = $max;
        }

        $this->quantity = $new;

        return $this->quantity;
    }

    public function decrement(int $amount = 1): int
    {
        $new = $this->quantity - $amount;
        $max = $this->product()?->maxAmountPerOrder();

        if ($max && $new > $max) {
            $new = $max;
        }

        $this->quantity = $new;

        if ($this->quantity <= 0) {
            $this->quantity = 0;
        }

        return $this->quantity;
    }

    public function key(): string // Merx
    {
        return $this->id();
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

    public function hasStockForQuantity(): bool
    {
        $stock = $this->product()?->stock(withHold: true);

        if (is_string($stock)) { // unknown stock = unlimited
            return true;
        }

        return is_numeric($stock) && $stock >= $this->quantity;
    }

    public function toArray(): array
    {
        return [
            // 'id' => $this->id(), // can be inferred from key of array
            'quantity' => $this->quantity(),
        ];
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function price(): float
    {
        return $this->product() ? $this->product()->price()->toFloat() : 0;
    }

    public function subtotal(): float
    {
        return $this->price() * $this->quantity();
    }

    public function formattedPrice(): string
    {
        return Kart::formatCurrency($this->price());
    }

    public function formattedSubtotal(): string
    {
        return Kart::formatCurrency($this->subtotal());
    }
}
