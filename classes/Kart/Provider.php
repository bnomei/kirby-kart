<?php

namespace Bnomei\Kart;

use Closure;
use Kirby\Cms\App;
use Kirby\Cms\User;
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

    public function updatedAt(): string
    {
        return date('c'); // TODO
    }

    public function option($key, bool $resolveCallables = true): mixed
    {
        $option = $this->kirby->option("bnomei.kart.providers.{$this->name}.$key");
        if ($resolveCallables && $option instanceof Closure) {
            return $option();
        }

        return $option;
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
