<?php

namespace Bnomei\Kart;

use Kirby\Cms\Collection;
use Kirby\Session\Session;
use ProductPage;

class Cart
{
    private Collection $lines;

    private Session $session;

    public function __construct(private string $id, array $items = [])
    {
        $this->session = kirby()->session();

        if (empty($items)) {
            $items = $this->session->get($this->id, []);
        }

        $this->lines = new Collection(array_map(
            fn ($i) => new CartLine($i['id'], $i['quantity']),
            $items
        ));
    }

    public function save(): void
    {
        $this->session->set($this->id, $this->lines->toArray());
    }

    public function add(?ProductPage $product, int $amount = 1): int
    {
        if (! $product) {
            return 0;
        }

        /** @var CartLine $item */
        if ($item = $this->lines->get($product->uuid()->id())) {
            $item->increment($amount);
        } else {
            $item = new CartLine(
                $product->uuid()->id(),
                $amount
            );
            $this->lines->add($item);
        }

        $this->save();

        return $item->quantity();
    }

    public function remove(?ProductPage $product, int $amount = 1): int
    {
        if (! $product) {
            return 0;
        }

        /** @var CartLine $item */
        $item = $this->lines->get($product->uuid()->id());
        if (! $item) {

            return 0;
        }

        if ($item->decrement($amount) <= 0) {
            $this->lines->remove($item);
            $item = null;
        }
        $this->save();

        return $item?->quantity() ?? 0;
    }

    public function lines(): Collection
    {
        return $this->lines;
    }

    public function count(): int
    {
        return $this->lines()->count();
    }

    public function quantity(): int
    {
        return (int) array_sum($this->lines->values(
            fn (CartLine $item) => $item->quantity()
        ));
    }

    public function sum(): float
    {
        return array_sum($this->lines->values(
            fn (CartLine $item) => $item->quantity() * $item->product()->price()->toFloat()
        ));
    }

    public function tax(): float
    {
        return array_sum($this->lines->values(
            fn (CartLine $item) => $item->quantity() * $item->product()->price()->toFloat() * $item->product()->tax()->toFloat() / 100.0
        ));
    }

    public function sumtax(): float
    {
        return $this->sum() + $this->tax();
    }

    public function has(ProductPage|CartLine|string $product): bool
    {
        if ($product instanceof ProductPage) {
            $product = $product->uuid()->id();
        }

        if ($product instanceof CartLine) {
            $product = $product->product()->uuid()->id();
        }

        return $this->lines->has($product);
    }
}
