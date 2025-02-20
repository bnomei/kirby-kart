<?php

namespace Bnomei\Kart;

use Closure;
use Kirby\Cms\App;
use Kirby\Cms\User;
use Kirby\Toolkit\Date;
use ProductPage;

use function env;

abstract class Provider implements ProviderInterface
{
    protected string $name;

    private App $kirby;

    public function __construct($kirby)
    {
        $this->kirby = $kirby;
    }

    public function title(): string
    {
        return ucfirst($this->name);
    }

    public function option($key, bool $resolveCallables = true): mixed
    {
        $option = $this->kirby->option("bnomei.kart.providers.{$this->name}.$key");
        if ($resolveCallables && $option instanceof Closure) {
            return $option();
        }

        return $option;
    }

    public function sync(ContentPageEnum|string|null $sync): int
    {
        $all = array_map(fn ($c) => $c->value, ContentPageEnum::cases());

        if (! $sync) {
            $sync = $all;
        }
        if ($sync instanceof ContentPageEnum) {
            $sync = [$sync->value];
        }
        if (is_string($sync)) {
            $sync = [$sync];
        }

        // only allow valid interfaces
        $sync = array_intersect($sync, $all);

        $t = microtime(true);

        foreach ($sync as $interface) {
            $this->$interface(); // calling the interface will trigger a refresh if necessary
        }

        return intval(round(($t - microtime(true)) * 1000));
    }

    public function updatedAt(ContentPageEnum|string|null $sync): string
    {
        $u = 'updatedAt';
        if ($sync instanceof ContentPageEnum) {
            $u .= '-'.$sync->value;
        } elseif (is_string($sync)) {
            $u .= '-'.$sync;
        }

        return $this->cache()->get($u, '?');
    }

    public function cache(): \Kirby\Cache\Cache
    {
        return $this->kirby->cache('bnomei.kart.'.$this->name);
    }

    public function fetch(string $interface): array
    {
        if ($data = $this->cache()->get($interface)) {
            return $data;
        }

        $method = 'fetch'.ucfirst($interface);
        $data = $this->$method(); // concrete implementation

        $expire = $this->kirby->option('bnomei.kart.expire');
        if (! is_null($expire)) {
            $this->cache()->set($interface, intval($expire));
        }

        // update timestamp
        $t = str_replace('+00:00', '', Date::now()->toString());
        $this->cache()->set('updatedAt', $t);
        $this->cache()->set('updatedAt-'.$interface, $t);

        return $data;
    }

    public function products(): array
    {
        return $this->fetch('products');
    }

    public function orders(): array
    {
        return $this->fetch('orders');
    }

    public function stocks(): array
    {
        return $this->fetch('stocks');
    }

    public function ownsProduct(ProductPage|string|null $product, ?User $user = null): bool
    {
        if (is_string($product)) {
            $product = $this->kirby->page($product);
        }

        if (! $product) {
            return false;
        }

        $user ??= $this->kirby->user();

        if (! $user) {
            return false;
        }

        // search the user account content (KLUB or other fulfillment)
        if ($user->hasMadePaymentFor($this->name, $product)) {
            return true;
        }

        // search orders
        return $user->completedOrders()
            ->filterBy(fn ($order) => $order->has($product))
            ->count() > 0;
    }

    protected function env(string $env, mixed $default = null): mixed
    {
        return env($env, $default);
    }
}
