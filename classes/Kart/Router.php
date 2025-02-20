<?php

namespace Bnomei\Kart;

use Closure;
use Kirby\Cms\Response;
use Kirby\Http\Uri;
use Kirby\Toolkit\A;
use ProductPage;

class Router
{
    const CHECKOUT = 'kart/checkout';

    const LOGIN = 'kart/login';

    const LOGOUT = 'kart/logout';

    const CART_ADD = 'kart/cart/add';

    const CART_REMOVE = 'kart/cart/remove';

    const WISHLIST_ADD = 'kart/wishlist/add';

    const WISHLIST_REMOVE = 'kart/wishlist/remove';

    public static function denied(): ?Response
    {
        $middlewares = option('bnomei.kart.middlewares');

        if ($middlewares instanceof Closure) {
            $middlewares = $middlewares();
        }

        if (! is_array($middlewares)) {
            $middlewares = [];
        }

        if (! Router::middlewares($middlewares)) {
            return Response::json([], 401);
        }

        return null;
    }

    public static function middlewares(array $middlewares = []): bool
    {
        $allowed = true;
        foreach ($middlewares as $middleware) {
            [$class, $method] = explode('::', $middleware);
            $allowed = $allowed && $class::$method();
        }

        return $allowed;
    }

    public static function ratelimit(): bool
    {
        if (! kirby()->option('bnomei.kart.ratelimit.enabled')) {
            return true;
        }

        return Ratelimit::check(kirby()->visitor()->ip());
    }

    public static function csrf(): bool
    {
        if (! kirby()->option('bnomei.kart.csrf.enabled')) {
            return true;
        }

        return csrf(self::get('token'));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $request = kirby()->request();
        if ($q = $request->get('q')) {
            $result = self::decrypt($q);

            return is_array($result) ? A::get($result, $key, $default) : $request->get($key, $default);
        }

        return $request->get($key, $default);
    }

    public static function decrypt(string $props): mixed
    {
        return Helper::decrypt($props, option('bnomei.kart.router.encryption'), true);
    }

    public static function checkout(): string
    {
        return Uri::index()->clone([
            'path' => self::CHECKOUT,
            'query' => static::queryCsrf() + self::encrypt([]),
        ])->toString();
    }

    protected static function queryCsrf(): array
    {
        if (! kirby()->option('bnomei.kart.csrf.enabled')) {
            return [];
        }

        return [
            'token' => csrf(),
        ];
    }

    public static function encrypt(array $query): array
    {
        $password = option('bnomei.kart.router.encryption');
        if ($password instanceof Closure) {
            $password = $password();
        }

        if (! $password) {
            return $query;
        }

        return [
            'q' => Helper::encrypt($query, $password, true),
        ];
    }

    public static function login(): string
    {
        return Uri::index()->clone([
            'path' => self::LOGIN,
            'query' => static::queryCsrf(),
        ])->toString();
    }

    public static function logout(): string
    {
        return Uri::index()->clone([
            'path' => self::LOGOUT,
            'query' => static::queryCsrf(),
        ])->toString();
    }

    public static function cart_add(ProductPage $product): string
    {
        return Uri::index()->clone([
            'path' => self::current().'/'.self::CART_ADD,
            'query' => static::queryCsrf() + self::encrypt([
                'product' => $product->uuid()->id(),
            ]),
        ])->toString();
    }

    public static function current(): string
    {
        return kirby()->request()->path();
    }

    public static function cart_remove(ProductPage $product): string
    {
        return Uri::index()->clone([
            'path' => self::current().'/'.self::CART_REMOVE,
            'query' => static::queryCsrf() + self::encrypt([
                'product' => $product->uuid()->id(),
            ]),
        ])->toString();
    }

    public static function wishlist_add(ProductPage $product): string
    {
        return Uri::index()->clone([
            'path' => self::current().'/'.self::WISHLIST_ADD,
            'query' => static::queryCsrf() + self::encrypt([
                'product' => $product->uuid()->id(),
            ]),
        ])->toString();
    }

    public static function wishlist_remove(ProductPage $product): string
    {
        return Uri::index()->clone([
            'path' => self::current().'/'.self::WISHLIST_REMOVE,
            'query' => static::queryCsrf() + self::encrypt([
                'product' => $product->uuid()->id(),
            ]),
        ])->toString();
    }
}
