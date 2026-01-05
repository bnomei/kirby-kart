<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Bnomei\Kart\Models\ProductPage;
use Kirby\Cms\Page;
use Kirby\Toolkit\A;

/**
 * Kart Cart Line
 */
class CartLine implements Kerbs
{
    private ?Page $product = null;

    public function __construct(
        private ProductPage|Page|string $uuid, // need to be named `id` for Collections to use it as key
        private int $quantity = 1,
        private ?string $variant = null,
        private readonly ?Cart $cart = null,
    ) {
        if ($this->uuid instanceof ProductPage) {
            $this->product = $this->uuid;
            // id is expected to be the uuid in all cases!
            $this->uuid = $uuid->uuid()->id(); // @phpstan-ignore-line
        } elseif (is_string($this->uuid)) {
            $this->product = page('page://'.$this->uuid) instanceof ProductPage ? page('page://'.$this->uuid) : null;
        }
    }

    /**
     * makes the product unique in the cart line collection
     */
    public function id(): string
    {
        return ($this->product()?->uuid()->id() ?? $this->uuid).($this->variant ? '|'.$this->variant : '');
    }

    public function product(bool $refresh = false): ?ProductPage
    {
        if ($refresh) {
            $this->product = page('page://'.$this->uuid) instanceof ProductPage ? page('page://'.$this->uuid) : null;
        }

        return $this->product; // @phpstan-ignore-line
    }

    public function increment(int $amount = 1): int
    {
        return $this->setQuantity($this->quantity + $amount);
    }

    public function setQuantity(int $amount = 1, bool $force = false): int
    {
        $new = $amount;
        $max = $this->product()?->maxAmountPerOrder();

        if (! $force && $max && $new > $max) {
            $new = $max;
            kart()->message('bnomei.kart.max-amount-per-order', 'cart');
        }

        $this->quantity = $new;

        if ($this->quantity < 0) {
            $this->quantity = 0;
        }

        return $this->quantity;
    }

    public function decrement(int $amount = 1): int
    {
        return $this->setQuantity($this->quantity - $amount);
    }

    public function fix(): bool
    {
        $old = $this->quantity();
        $new = $old;

        if (! $this->hasStockForQuantity()) {
            $stock = $this->product()?->stock(withHold: $this->cart?->sessionToken(), variant: $this->variant);
            $new = is_numeric($stock) ? $this->setQuantity(intval($stock)) : $old;
        }

        $updated = $this->setQuantity($new); // call will enforce maxapo even with same quantity

        return $old !== $updated;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function variant(): ?string
    {
        return $this->variant;
    }

    public function hasStockForQuantity(): bool
    {
        $stock = $this->product()?->stock(withHold: $this->cart?->sessionToken(), variant: $this->variant);

        if (is_string($stock)) { // unknown stock = unlimited
            return true;
        }

        return is_numeric($stock) && $stock >= $this->quantity;
    }

    public function key(): string // Merx
    {
        return $this->id();
    }

    public function toArray(): array
    {
        return [
            // 'id' => $this->id(), // can be inferred from key of array in the CART (not cartline)
            'quantity' => $this->quantity(),
        ];
    }

    public function formattedPrice(): string
    {
        return Kart::formatCurrency($this->price());
    }

    public function price(): float
    {
        if (! $this->product()) {
            return 0;
        }

        $price = $this->product()->price()->toFloat();
        $variant = $this->variant();
        if ($this->product()->hasVariant($variant)) {
            $matches = array_values(array_filter(
                $this->product()->variantData(),
                fn ($v) => $v['variant'] === $variant
            ));
            if (count($matches)) {
                $price = A::get($matches[0], 'price', $price);
            }
        }

        return $price;
    }

    public function formattedSubtotal(): string
    {
        return Kart::formatCurrency($this->subtotal());
    }

    public function subtotal(): float
    {
        return $this->price() * $this->quantity();
    }

    protected ?array $kerbs = null;

    public function toKerbs(): array
    {
        if ($this->kerbs) {
            return $this->kerbs;
        }

        return $this->kerbs = array_filter([
            'formattedPrice' => $this->formattedPrice(),
            'formattedSubtotal' => $this->formattedSubtotal(),
            'hasStockForQuantity' => $this->hasStockForQuantity(),
            'price' => $this->price(),
            'product' => $this->product()?->toKerbs(full: false),
            'quantity' => $this->quantity(),
            'subtotal' => $this->subtotal(),
            'variant' => $this->variant(),
        ], fn ($value) => $value !== null);
    }
}
