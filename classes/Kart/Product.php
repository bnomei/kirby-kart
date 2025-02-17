<?php

namespace Bnomei\Kart;

use Kirby\Cms\StructureObject;

class Product
{
    public function __construct(
        public string $id,
        private float $price,
        private float $taxrate,
        private int $quantity,
    ) {
        $this->quantity = 1;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function increment(): int
    {
        $this->quantity++;

        return $this->quantity;
    }

    public function decrement(): int
    {
        $this->quantity--;

        return $this->quantity;
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
        return $this->price;
    }

    public function taxrate(): float
    {
        return $this->taxrate;
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

    public function availability(): ?string
    {
        // https://schema.org/ItemAvailability
        return $this->hasStock() ? 'InStock' : 'OutOfStock';
    }

    public function hasStock(): bool
    {
        if (! kirby()->option('bnomei.kart.stocks.enabled')) {
            return true;
        }

        // NOTE: the product.id is expected to match the productpage.uuid
        /** @var StructureObject $stock */
        foreach (kart()->page('stocks')->stocks()->toStructure() as $stock) {
            if ($stock->product()->id() === 'page://'.$this->id()) {
                return $stock->stock() > 0;
            }
        }

        return true; // no stock entry means it is InStock
    }
}
