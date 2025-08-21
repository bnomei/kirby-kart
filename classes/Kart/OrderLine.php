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

/**
 * @method string key() aka id(), the link to the product
 * @method float price() Price of a single item
 * @method int quantity() Amount of items in this line
 * @method float total() (price * quantity) - discount + tax
 * @method float subtotal() price * quantity
 * @method float tax() as monetary amount not as tax-rate
 * @method float discount() as monetary amount not as percentage
 */
class OrderLine implements Kerbs
{
    private readonly ?Page $product;

    public function __construct(
        private readonly string $id, // need to be named `id` for Collections to use it as key
        private readonly float $price = 0,
        private readonly int $quantity = 0,
        private readonly float $total = 0,
        private readonly float $subtotal = 0,
        private readonly float $tax = 0,
        private readonly float $discount = 0,
        private readonly ?string $licensekey = null,
        private readonly ?string $variant = null,
    ) {
        $p = explode('|', $this->id); // id might contain variant to be unique in collection
        $this->product = kirby()->page($p[0]);
    }

    public function __call(string $name, array $arguments): mixed
    {
        if ($name == 'key') { // Merx
            return $this->id();
        }
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        return null;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function product(): ?ProductPage
    {
        return $this->product; // @phpstan-ignore-line
    }

    public function licensekey(): ?string
    {
        return $this->licensekey;
    }

    public function variant(): ?string
    {
        return $this->variant;
    }

    public function formattedPrice(): string
    {
        return Kart::formatCurrency($this->price);
    }

    public function formattedTax(): string
    {
        return Kart::formatCurrency($this->tax);
    }

    public function formattedTotal(): string
    {
        return Kart::formatCurrency($this->total);
    }

    public function formattedSubtotal(): string
    {
        return Kart::formatCurrency($this->subtotal);
    }

    public function formattedDiscount(): string
    {
        return Kart::formatCurrency($this->discount);
    }

    protected ?array $kerbs = null;

    public function toKerbs(): array
    {
        if ($this->kerbs) {
            return $this->kerbs;
        }

        return $this->kerbs = array_filter([
            'discount' => $this->discount(),
            'formattedDiscount' => $this->formattedDiscount(),
            'formattedPrice' => $this->formattedPrice(),
            'formattedSubtotal' => $this->formattedSubtotal(),
            'formattedTax' => $this->formattedTax(),
            'formattedTotal' => $this->formattedTotal(),
            'licensekey' => $this->licensekey,
            'price' => $this->price(),
            'product' => $this->product()?->toKerbs(),
            'quantity' => $this->quantity(),
            'subtotal' => $this->subtotal(),
            'tax' => $this->tax(),
            'total' => $this->total(),
            'variant' => $this->variant(),
        ]);
    }
}
