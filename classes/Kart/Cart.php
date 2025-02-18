<?php

namespace Bnomei\Kart;

use Kirby\Cms\Collection;
use Kirby\Cms\User;
use Kirby\Session\Session;
use Kirby\Toolkit\A;
use ProductPage;

class Cart
{
    private Collection $lines;

    private Session $session;

    private ?User $user = null;

    private string $id; // this will match the field on the user content (cart, wishlist)

    public function __construct(string $id, array $items = [])
    {
        $this->id = $id;
        $this->session = kirby()->session();

        if (empty($items)) {
            $items = $this->session->get($this->id, []);
        }

        $this->lines = new Collection;
        foreach ($items as $id => $line) {
            $this->add(page('page://'.$id), A::get($line, 'quantity'));
        }
    }

    public function save(): void
    {
        $this->session->set($this->id, $this->lines->toArray());
        if ($user = kirby()->user()) {
            $this->user = $user;
        }
        if ($this->user) {
            kirby()->impersonate('kirby', function () {
                $this->user->update([
                    $this->id => $this->lines->toArray(),
                ]);
            });
        }
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

    public function clear(): void
    {
        $this->lines = new Collection;
        $this->save();
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

    public function merge(User $user): bool
    {
        $this->user = $user;

        $cart = $user->cart();
        if ($cart->isEmpty()) {
            return true; // no plans to merge
        }

        $lines = $cart->yaml();
        if (! is_array($lines)) {
            return false; // failed to get array
        }

        foreach ($lines as $id => $line) {
            $this->add(page('page://'.$id), A::get($line, 'quantity'));
        }

        return true;
    }
}
