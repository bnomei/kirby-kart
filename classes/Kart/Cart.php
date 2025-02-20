<?php

namespace Bnomei\Kart;

use Kirby\Cms\App;
use Kirby\Cms\Collection;
use Kirby\Cms\User;
use Kirby\Content\Field;
use Kirby\Toolkit\A;
use ProductPage;

class Cart
{
    private Collection $lines;

    private ?User $user = null;

    private string $id; // this will match the field on the user content (cart, wishlist)

    private App $kirby;

    public function __construct(string $id, array $items = [])
    {
        $this->id = $id;
        $this->kirby = kirby();

        if (empty($items)) {
            $items = $this->kirby->session()->get($this->id, []);
        }

        $this->lines = new Collection;
        foreach ($items as $uuid => $line) {
            $this->add(
                $this->kirby->page('page://'.$uuid),
                A::get($line, 'quantity')
            );
        }

        kirby()->trigger('kart.'.$this->id.'.created', [
            $this->id => $this,
        ]);
    }

    public function add(ProductPage|array|string|null $product, int $amount = 1): int
    {
        if (! $product) {
            return 0;
        }

        // Merx compatibility
        if (is_array($product)) {
            $product = $this->kirby->page($product['id']);
            $amount = A::get($product, 'quantity', $amount);
        }

        if (is_string($product)) {
            $product = $this->kirby->page($product);
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

        $this->kirby->trigger('kart.'.$this->id.'.add', [
            'user' => $this->user,
            'item' => $item,
            'product' => $product,
            'count' => $this->lines->count(),
        ]);

        return $item->quantity();
    }

    public function save(): void
    {
        $this->kirby->session()->set($this->id, $this->lines->toArray());
        if ($user = $this->kirby->user()) {
            $this->user = $user;
        }
        if ($this->user) {
            $this->kirby->impersonate('kirby', function () {
                $this->user->update([
                    $this->id => $this->lines->toArray(),
                ]);
            });
        }
    }

    public function count(): int
    {
        return $this->lines()->count();
    }

    public function lines(): Collection
    {
        return $this->lines;
    }

    public function quantity(): int
    {
        return (int) array_sum($this->lines->values(
            fn (CartLine $item) => $item->quantity()
        ));
    }

    public function remove(ProductPage|array|string|null $product, int $amount = 1): int
    {
        if (! $product) {
            return 0;
        }

        // Merx compatibility
        if (is_array($product)) {
            $product = $this->kirby->page($product['id']);
            $amount = A::get($product, 'quantity', $amount);
        }

        if (is_string($product)) {
            $product = $this->kirby->page($product);
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

        $this->kirby->trigger('kart.'.$this->id.'.remove', [
            'user' => $this->user,
            'item' => $item,
            'product' => $product,
            'count' => $this->lines->count(),
        ]);

        return $item?->quantity() ?? 0;
    }

    public function delete(): void
    {
        // alias for Merx compatibility
        $this->clear();
    }

    public function clear(): void
    {
        $this->lines = new Collection;
        $this->save();

        $this->kirby->trigger('kart.'.$this->id.'.clear', [
            'user' => $this->user,
        ]);
    }

    public function getSum(): float
    {
        // Merx compatiblity
        return $this->sum();
    }

    public function sum(): float
    {
        return array_sum($this->lines->values(
            fn (CartLine $item) => $item->quantity() *
                $item->product()->price()->toFloat()
        ));
    }

    public function getTax(): float
    {
        // Merx compatiblity
        return $this->tax();
    }

    public function tax(): float
    {
        return array_sum($this->lines->values(
            fn (CartLine $item) => $item->quantity() *
                $item->product()->price()->toFloat() *
                $item->product()->tax()->toFloat() /
                100.0
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

        /** @var Field $cart */
        $cart = $user->cart();
        if ($cart->isEmpty()) {
            return true; // no plans to merge
        }

        $lines = $cart->yaml();
        if (! is_array($lines)) {
            return false; // failed to get array
        }

        foreach ($lines as $uuid => $line) {
            $this->add(
                $this->kirby->page('page://'.$uuid),
                A::get($line, 'quantity')
            );
        }

        return true;
    }
}
