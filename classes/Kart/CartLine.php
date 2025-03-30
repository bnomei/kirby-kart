<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Kirby\Cms\Page;
use ProductPage;

class CartLine
{
    private ?Page $product = null;

    public function __construct(
        private ProductPage|Page|string|null $uuid, // need to be named `id` for Collections to use it as key
        private int $quantity = 1,
    ) {
        if ($uuid instanceof ProductPage) {
            $this->product = $uuid;
            // id is expected to be the uuid in all cases!
            $this->uuid = $uuid->uuid()->id();
        } elseif (is_string($uuid)) {
            $this->product = page('page://'.$this->uuid)->template()->name() === 'product' ? page('page://'.$this->uuid) : null;
        }
    }

    public function increment(int $amount = 1): int
    {
        return $this->setQuantity($this->quantity + $amount);
    }

    public function decrement(int $amount = 1): int
    {
        return $this->setQuantity($this->quantity - $amount);
    }

    public function setQuantity(int $amount = 1): int
    {
        $new = $amount;
        $max = $this->product()?->maxAmountPerOrder();

        if ($max && $new > $max) {
            $new = $max;
            kart()->message('bnomei.kart.max-amount-per-order', 'cart');
        }

        $this->quantity = $new;

        if ($this->quantity < 0) {
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
        return $this->product()?->uuid()->id() ?? $this->uuid;
    }

    public function product(bool $refresh = false): ProductPage|Page|null
    {
        if ($refresh) {
            $this->product = page('page://'.$this->uuid)->template()->name() === 'product' ? page('page://'.$this->uuid) : null;
        }

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
            // 'id' => $this->id(), // can be inferred from key of array in the CART (not cartline)
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
