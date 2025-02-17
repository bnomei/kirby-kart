<?php

namespace Bnomei\Kart;

use Kirby\Cms\Collection;

class Cart
{
    private Collection $products;

    public function __construct(array $items = [])
    {
        $this->products = new Collection($items);
    }

    public function add(Product $item): int
    {
        if ($item = $this->products->get($item->id())) {
            $item->increment();
        } else {
            $this->products->append($item);
        }

        return $item->quantity();
    }

    public function remove(Product $item): int
    {
        $item = $this->products->get($item->id());
        if (! $item) {
            return 0;
        }

        if ($item->decrement() === 0) {
            $this->products->remove($item);

            return 0;
        }

        return $item->quantity();
    }

    public function products(): Collection
    {
        return $this->products;
    }

    public function count(): int
    {
        return $this->products()->count();
    }

    public function quantity(): int
    {
        return (int) array_sum($this->products->values(
            fn (Product $item) => $item->quantity()
        ));
    }

    public function sum(): float
    {
        return array_sum($this->products->values(
            fn (Product $item) => $item->quantity() * $item->price()
        ));
    }

    public function tax(): float
    {
        return array_sum($this->products->values(
            fn (Product $item) => $item->quantity() * $item->tax()
        ));
    }

    public function sumtax(): float
    {
        return $this->sum() + $this->tax();
    }

    public function has(Product|string $product): bool
    {
        if ($product instanceof Product) {
            $product = $product->id();
        }

        return $this->products->has($product);
    }
}
