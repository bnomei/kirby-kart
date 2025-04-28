<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Kirby\Cms\App;
use Kirby\Cms\Collection;
use Kirby\Cms\User;
use Kirby\Toolkit\A;
use Kirby\Uuid\Uuid;
use OrderPage;
use OrdersPage;
use ProductPage;
use StocksPage;

class Cart implements Kerbs
{
    /** @var Collection<CartLine> */
    private Collection $lines; // this will match the field on the user content (cart, wishlist)

    private readonly App $kirby;

    private readonly Kart $kart;

    private ?string $sid = null;

    public function __construct(private readonly string $id = 'cart', array $items = [])
    {
        $this->kirby = kirby();
        $this->kart = kirby()->site()->kart();

        if (empty($items)) {
            $items = $this->kirby->session()->get($this->id, []);
        }

        $this->lines = new Collection;
        foreach ($items as $uuid => $line) {
            $variant = null;
            if (str_contains($uuid, '|')) {
                list($uuid, $variant) = explode('|', $uuid);
            }
            $this->add(
                $this->kirby->page($uuid) ?? $this->kirby->page('page://'.$uuid),
                A::get($line, 'quantity'),
                false,
                A::get($line, 'variant', $variant)
            );
        }
        // NOTE: do NOT save here, handled separately
        /*
        if ($this->lines->count()) {
            $this->save();
        }
        */

        kirby()->trigger('kart.'.$this->id.'.created', [
            $this->id => $this,
        ]);
    }

    public function add(ProductPage|array|string|null $product, int $amount = 1, bool $set = false, ?string $variant = null): int
    {
        // Merx compatibility
        if (is_array($product)) {
            $amount = intval(A::get($product, 'quantity', $amount));
            $product = $this->kirby->page($product['id']);
        }

        if (is_string($product)) {
            $product = $this->kirby->page($product) ?? $this->kirby->page('page://'.$product);
        }

        if (! $product) {
            return 0;
        }

        $maxLines = intval(kart()->option('orders.order.maxlpo'));
        if ($item = $this->lines->get($product->uuid()->id().($variant ? '|'.$variant : ''))) {
            /** @var CartLine $item */
            if ($set) {
                $a = $item->setQuantity($amount);
            } else {
                $a = $item->increment($amount);
            }
            if ($a <= 0) {
                $this->lines->remove($item);
            }
        } else {
            $item = new CartLine(
                $product->uuid()->id(),
                $amount,
                $variant,
                $this
            );
            $this->lines->add($item);
        }

        if ($this->lines->count() >= $maxLines) {
            $this->lines = $this->lines->flip()->slice(0, $maxLines);
        }

        if (! $this->kirby->environment()->isLocal() && $this->kirby->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
            $this->lines = $this->lines->flip()->slice(0, 1);
        }

        // $this->save(); // responsibility deferred to callee

        $this->kirby->trigger('kart.'.$this->id.'.add', [
            'product' => $product,
            'count' => $this->lines->count(),
            'item' => $item,
            'user' => $this->kirby->user(),
            'variant' => $variant,
        ]);

        return $item->quantity();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function hash(): string
    {
        return Kart::hash($this->lines()->toJson());
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

    public function fix(): void
    {
        /** @var CartLine $line */
        foreach ($this->lines as $line) {
            $line->fix();
        }
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
            if ($line->quantity() > ($line->product()?->maxAmountPerOrder() ?? intval(kart()->option('orders.order.maxapo')))) {
                kart()->message('bnomei.kart.max-amount-per-order', 'checkout');

                return false;
            }

            $stock = $line->product()?->stock(withHold: $this->sessionToken(), variant: $line->variant());
            if (is_int($stock) && $stock < $line->quantity()) {
                kart()->message('bnomei.kart.out-of-stock', 'checkout');

                return false;
            }
        }

        return true;
    }

    public function sessionToken(?string $token = null): string
    {
        if ($token) {
            $this->sid = $token;
        }

        if (! $this->sid) {
            $this->sid = $this->kirby->session()->token() ?? Uuid::generate();
        }

        return $this->sid;
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    public function isEmpty(): bool
    {
        return $this->lines()->count() === 0;
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

    public function remove(ProductPage|array|string|null $product, int $amount = 1, ?string $variant = null): int
    {
        // Merx compatibility
        if (is_array($product)) {
            $amount = intval(A::get($product, 'quantity', $amount));
            $product = $this->kirby->page($product['id']) ?? $this->kirby->page('page://'.$product['id']);
        }

        if (is_string($product)) {
            $product = $this->kirby->page($product) ?? $this->kirby->page('page://'.$product);
        }

        if (! $product) {
            return 0;
        }

        /** @var CartLine|null $item */
        $item = $this->lines->get($product->uuid()->id().($variant ? '|'.$variant : ''));
        if (is_null($item)) {
            return 0;
        }

        if ($item->decrement($amount) <= 0) {
            $this->lines->remove($item);
            $item = null;
        }

        // $this->save(); // responsibility deferred to callee

        $this->kirby->trigger('kart.'.$this->id.'.remove', [
            'product' => $product,
            'count' => $this->lines->count(),
            'item' => $item,
            'user' => $this->kirby->user(),
            'variant' => $variant,
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
        // $this->save(); // responsibility deferred to callee

        $this->kirby->trigger('kart.'.$this->id.'.clear', [
            'user' => $this->kirby->user(),
        ]);
    }

    public function has(ProductPage|CartLine|string $product, ?string $variant = null): bool
    {
        if ($product instanceof ProductPage) {
            $product = $product->uuid()->id();
        }

        if ($product instanceof CartLine) {
            $product = $product->product()?->uuid()->id();
        }

        return $product && $this->lines->has($product.($variant ? '|'.$variant : ''));
    }

    public function merge(User $user): bool
    {
        if ($user->isCustomer() === false) {
            return false; // no merging for customers
        }

        // refresh the user in case the cart changed
        $user = $this->kirby->user($user->id());

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
                $this->kirby->page($uuid) ?? $this->kirby->page('page://'.$uuid),
                A::get($line, 'quantity'),
                false,
                A::get($line, 'variant'),
            );
        }
        // NOTE: done by callee
        /*
        if ($this->lines()->count()) {
            $this->save();
        }
        */

        return true;
    }

    public function complete(): string
    {
        $data = $this->kart->provider()->completed();

        $customer = $this->kart->createOrUpdateCustomer($data);

        /** @var OrdersPage|null $orders */
        $orders = $this->kart->page(ContentPageEnum::ORDERS);
        /** @var OrderPage|null $order */
        $order = $orders?->createOrder($data, $customer);
        $order?->createZipWithFiles();

        $this->releaseStock($data);
        /** @var StocksPage|null $stocks */
        $stocks = $this->kart->page(ContentPageEnum::STOCKS);
        $stocks?->updateStocks($data, -1);

        $this->kirby->trigger('kart.cart.completed', [
            'user' => $customer,
            'order' => $order,
        ]);

        $this->clear();
        $this->save();

        return $this->kirby->session()->pull(
            'kart.redirect.success',
            $order ? $order->url() : $this->kirby->site()->url()
        );
    }

    public function releaseStock(?array $data = null): bool
    {
        if (! $data) {
            $data = ['items' => $this->lines->toArray(
                fn ($line) => [
                    'key' => [$line->product()->uuid()->id()],
                    'quantity' => $line->quantity(),
                    'variant' => $line->variant(),
                ]
            )];
        }
        $expire = kart()->option('stocks.hold');
        if (! is_numeric($expire) || ! kirby()->user()?->isCustomer()) {
            return false;
        }

        $hasOne = false;
        foreach ($data['items'] as $item) {
            $product = $this->kirby->page($item['key'][0]) ?? $this->kirby->page('page://'.$item['key'][0]);
            if (! $product) {
                continue;
            }
            $variant = A::get($item, 'variant');
            $holdKey = 'hold-'.Kart::hash($product->uuid()->toString().($variant ? '|'.$variant : ''));
            $holds = $this->kirby->cache('bnomei.kart.stocks-holds')->get($holdKey, []);
            $sid = $this->sessionToken();
            if (array_key_exists($sid, $holds)) {
                unset($holds[$sid]);
                $hasOne = true;
            } else {
                // find by product and quantity, sort by expiry desc (as the best guess)
                $holds = array_filter($holds, fn ($hold) => $hold['product'] === $product->uuid()->id() &&
                    $hold['quantity'] === intval($item['quantity']) &&
                    (! $variant || $hold['variant'] === $variant)
                );
                $holds = A::sort($holds, 'expires', 'desc');
                if (count($holds) > 0) {
                    array_shift($holds);
                    $hasOne = true;
                }
            }
            $this->kirby->cache('bnomei.kart.stocks-holds')->set($holdKey, $holds, intval($expire));
        }

        return $hasOne;
    }

    public function save(bool $writeToUser = true): void
    {
        $this->kirby->session()->set($this->id, $this->lines->toArray());

        // NOTE: no impersonation as that would shift to the kirby user.
        // retrieve a mutable copy now, just $this->kirby->user() fails.
        $user = $this->kirby->user() ? $this->kirby->user($this->kirby->user()->id()) : null;
        $writeToUser && $user?->update([
            'kart_'.$this->id => $this->lines->toArray(),
        ]);
    }

    public function holdStock(): bool
    {
        $expire = kart()->option('stocks.hold');
        if (! is_numeric($expire) || ! kirby()->user()?->isCustomer()) {
            return false;
        }

        $hasOne = false;
        /** @var CartLine $line */
        foreach ($this->lines as $line) {
            $product = $line->product();
            if (! $product) {
                continue;
            }

            $variant = $line->variant();
            $holdKey = 'hold-'.Kart::hash($product->uuid()->toString().($variant ? '|'.$variant : ''));
            $holds = $this->kirby->cache('bnomei.kart.stocks-holds')->get($holdKey, []);
            $holds = array_filter($holds, fn ($hold) => $hold['expires'] > time()); // discard outdated
            $sid = $this->sessionToken();
            $holds[$sid] = array_filter([
                'product' => $product->uuid()->id(),
                'variant' => $variant,
                'expires' => time() + $expire * 60,
                'quantity' => $line->quantity(),
            ]);

            // the cache does not have to live longer than the expiry of each line
            $this->kirby->cache('bnomei.kart.stocks-holds')->set($holdKey, $holds, intval($expire));
            $hasOne = true;
        }

        return $hasOne;
    }

    public function toKerbs(): array
    {
        return array_filter([
            'canCheckout' => $this->canCheckout(),
            'count' => $this->lines()->count(),
            'formattedSubtotal' => $this->formattedSubtotal(),
            'hash' => $this->hash(),
            'id' => $this->id,
            'lines' => $this->lines()->values(fn (CartLine $l) => $l->toKerbs()),
            'quantity' => $this->quantity(),
            'subtotal' => $this->subtotal(),
            'url' => page($this->id)?->url(),
        ]);
    }
}
