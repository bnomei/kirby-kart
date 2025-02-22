<?php

namespace Bnomei\Kart;

use Kirby\Cms\App;
use Kirby\Cms\Collection;
use Kirby\Cms\Page;
use Kirby\Cms\User;
use Kirby\Content\Field;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use Kirby\Toolkit\V;
use OrderPage;
use ProductPage;

class Cart
{
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

        if (! $this->kirby->environment()->isLocal() && $this->kirby->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
            $this->lines = $this->lines->flip()->slice(0, 1);
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

    public function complete(): string
    {
        $data = $this->kart->provider()->completed();

        $customer = $this->createCustomer($data);
        $order = $this->createOrder($data, $customer);
        $stocksChanged = $this->updateStock($data);

        $this->kirby->trigger('kart.cart.completed', [
            'customer' => $customer,
            'order' => $order,
            'stocksChanged' => $stocksChanged, // boolean
        ]);

        $this->clear();

        return $this->kirby->session()->pull(
            'kart.redirect',
            $order ? $order->url() : $this->kirby->site()->url()
        );
    }

    public function createCustomer(array $credentials): ?User
    {
        $email = A::get($credentials, 'email');
        $customer = $this->kirby->users()->findBy('email', $email);
        if (! $customer && V::email($email) && $this->kirby->option('bnomei.kart.customers.enabled')) {
            $customer = $this->kirby->impersonate('kirby', function () use ($credentials, $email) {
                return $this->kirby->users()->create([
                    'email' => $email,
                    'name' => A::get($credentials, 'name', ''),
                    'password' => Str::random(16),
                    'role' => $this->kirby->option('bnomei.kart.customers.roles')[0],
                ]);
            });
            $this->kirby->trigger('kart.user.created', ['user' => $customer]);
        }

        return $customer;
    }

    public function createOrder(array $data, ?User $customer): ?Page
    {
        if (! $this->kirby->option('bnomei.kart.orders.enabled')) {
            return null;
        }

        return OrderPage::create([
            // id, title, slug and uuid are automatically generated
            'content' => A::get($data, [
                'paidDate',
                'paymentMethod',
                'paymentComplete',
                'items',
            ]) + [
                'customer' => [$customer?->uuid()->toString()], // kirby user field expects an array
            ],
        ]);
    }

    public function updateStock(array $data): bool
    {
        $count = 0;
        foreach (A::get($data, 'items', []) as $item) {
            if (! is_array($item['key']) || count($item['key']) !== 1) {
                continue;
            }

            /** @var ?ProductPage $product */
            $product = $this->kirby->page($item['key'][0]);
            if ($product && $product->updateStock(intval($item['quantity']) * -1) !== null) {
                $count++;
            }
        }

        return $count > 0;
    }
}
