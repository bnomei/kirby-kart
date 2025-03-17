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
    /** @var Collection<CartLine> */
    private Collection $lines;

    private ?User $user = null;

    private string $id; // this will match the field on the user content (cart, wishlist)

    private App $kirby;

    private Kart $kart;

    public function __construct(string $id, array $items = [])
    {
        $this->id = $id;
        $this->kirby = kirby();
        $this->kart = kirby()->site()->kart();

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
        // Merx compatibility
        if (is_array($product)) {
            $product = $this->kirby->page($product['id']);
            $amount = A::get($product, 'quantity', $amount);
        }

        if (is_string($product)) {
            $product = $this->kirby->page($product);
        }

        if (! $product) {
            return 0;
        }

        if ($item = $this->lines->get($product->uuid()->id())) {
            /** @var CartLine $item */
            $item->increment($amount);
        } else {
            $item = new CartLine(
                $product->uuid()->id(),
                $amount
            );
            $this->lines->add($item);
        }

        if (! $this->kirby->environment()->isLocal() && $this->kirby->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
            $this->lines = $this->lines->flip()->slice(0, 1);
        }

        $this->save();

        $this->kirby->trigger('kart.'.$this->id.'.add', [
            'product' => $product,
            'count' => $this->lines->count(),
            'item' => $item,
            'user' => $this->user,
        ]);

        return $item->quantity();
    }

    public function canCheckout(): bool
    {
        if ($this->lines()->count() === 0) {
            return false;
        }

        /**
         * @var CartLine $line
         */
        foreach ($this->lines() as $line) {
            $stock = $line->product()?->stock();
            if (is_int($stock) && $stock < $line->quantity()) {
                kart()->message('bnomei.kart.out-of-stock', 'checkout');

                return false;
            }
        }

        return true;
    }

    public function save(): void
    {
        $this->kirby->session()->set($this->id, $this->lines->toArray());
        $user = $this->kirby->user();
        if ($user?->isCustomer()) {
            $this->user = $user;
        }
        $this->user?->update([
            'kart_'.$this->id => $this->lines->toArray(),
        ]);
    }

    public function count(): int
    {
        return $this->lines()->count();
    }

    /**
     * @return Collection<CartLine>
     */
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

    public function allInStock(): bool
    {
        foreach ($this->lines as $line) {
            /** @var CartLine $line */
            if (! $line->hasStockForQuantity()) {
                return false;
            }
        }

        return true;
    }

    public function formattedSubtotal(): string
    {
        return Kart::formatCurrency($this->subtotal());
    }

    public function subtotal(): float
    {
        return array_sum($this->lines->values(
            fn (CartLine $item) => $item->product() ? ($item->quantity() *
                $item->product()->price()->toFloat()) : 0
        ));
    }

    public function remove(ProductPage|array|string|null $product, int $amount = 1): int
    {
        // Merx compatibility
        if (is_array($product)) {
            $product = $this->kirby->page($product['id']);
            $amount = A::get($product, 'quantity', $amount);
        }

        if (is_string($product)) {
            $product = $this->kirby->page($product);
        }

        if (! $product) {
            return 0;
        }

        /** @var CartLine|null $item */
        $item = $this->lines->get($product->uuid()->id());
        if (is_null($item)) {
            return 0;
        }

        if ($item->decrement($amount) <= 0) {
            $this->lines->remove($item);
            $item = null;
        }

        $this->save();

        $this->kirby->trigger('kart.'.$this->id.'.remove', [
            'product' => $product,
            'count' => $this->lines->count(),
            'item' => $item,
            'user' => $this->user,
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

    public function has(ProductPage|CartLine|string $product): bool
    {
        if ($product instanceof ProductPage) {
            $product = $product->uuid()->id();
        }

        if ($product instanceof CartLine) {
            $product = $product->product()?->uuid()->id();
        }

        return $product && $this->lines->has($product);
    }

    public function merge(User $user): bool
    {
        if ($user->isCustomer() === false) {
            return false; // no merging for customers
        }

        $this->user = $user;

        $cartname = 'kart_'.$this->id;
        $cart = $user->$cartname();
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

    public function complete(): string
    {
        $data = $this->kart->provider()->completed();

        $customer = $this->kart->createOrUpdateCustomer($data);

        /** @var \OrdersPage|null $orders */
        $orders = $this->kart->page(ContentPageEnum::ORDERS);
        /** @var \OrderPage|null $order */
        $order = $orders?->createOrder($data, $customer);
        $order?->createZipWithFiles();

        /** @var \StocksPage|null $stocks */
        $stocks = $this->kart->page(ContentPageEnum::STOCKS);
        $stocks?->updateStocks($data);

        $this->kirby->trigger('kart.cart.completed', [
            'user' => $customer,
            'order' => $order,
        ]);

        $this->clear();

        return $this->kirby->session()->pull(
            'kart.redirect.success',
            $order ? $order->url() : $this->kirby->site()->url()
        );
    }
}
