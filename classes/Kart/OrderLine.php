<?php

namespace Bnomei\Kart;

use ProductPage;

/**
 * @method string key() aka id(), the link to the product
 * @method float price() Price of a single item
 * @method int quantity() Amount of items in this line
 * @method float total() (price * quantity) - discount + tax
 * @method float subtotal() price * quantity
 * @method float tax() as monetary amount not as tax-rate
 * @method float discount() as monetary amount not as percentage
 */
class OrderLine
{
    private ?ProductPage $product;

    public function __construct(
        private string $id, // need to be named `id` for Collections to use it as key
        private float $price = 0,
        private int $quantity = 0,
        private float $total = 0,
        private float $subtotal = 0,
        private float $tax = 0,
        private float $discount = 0,
    ) {
        $this->product = page($this->id); // @phpstan-ignore-line
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

    public function formattedPrice(): string
    {
        return Kart::formatCurrency($this->price);
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
}
