<?php

namespace Bnomei\Kart;

use Bnomei\Kart\Provider\Kirby;
use Bnomei\Kart\Provider\Mollie;
use Bnomei\Kart\Provider\Paddle;
use Bnomei\Kart\Provider\Stripe;
use Kirby\Cms\Collection;

class Kart
{
    private static ?Kart $singleton = null;

    private ?Provider $provider;

    private ?Cart $cart;

    private ?Cart $wishlist;

    public function provider(): Provider
    {
        if (! $this->provider) {
            $this->provider = match (kirby()->option('bnomei.kart.provider')) {
                'kirby' => new Kirby,
                'mollie' => new Mollie,
                'paypal' => new Paddle,
                'stripe' => new Stripe,
            };
        }

        return $this->provider;
    }

    public function cart(): Cart
    {
        if (! $this->cart) {
            $this->cart = new Cart;
        }

        return $this->cart;
    }

    public function wishlist(): Cart
    {
        if (! $this->wishlist) {
            $this->wishlist = new Cart;
        }

        return $this->wishlist;
    }

    public function checkout(): string
    {
        return Router::checkout();
    }

    public function login(): string
    {
        return Router::login();
    }

    public function logout(): string
    {
        return Router::logout();
    }

    public function products(): Collection
    {
        return $this->cart->products();
    }

    public function count(): int
    {
        return $this->cart->count();
    }

    public function amount(): int
    {
        return $this->cart->amount();
    }

    public function sum(): string
    {
        return Data::formatCurrency($this->cart->sum());
    }

    public function tax(): string
    {
        return Data::formatCurrency($this->cart->tax());
    }

    public function sumtax(): string
    {
        return Data::formatCurrency($this->cart->sumtax());
    }

    public static function singleton(): Kart
    {
        if (self::$singleton === null) {
            self::$singleton = new self;
        }

        return self::$singleton;
    }

    public static function flush(string $cache = 'all'): bool
    {
        if (kirby()->option('bnomei.kart.expire') === null) {
            return false;
        }

        try {
            $caches = [];
            if (empty($cache) || $cache === '*' || $cache === 'all') {
                $caches = array_keys(kirby()->option('bnomei.turbo.cache'));
            } else {
                $caches[] = $cache;
            }
            foreach ($caches as $c) {
                kirby()->cache('bnomei.kart.'.$c)->flush();
            }

            return true;
        } catch (\Exception $e) {
            // if given a cache that does not exist or is not flushable
            return false;
        }
    }
}
