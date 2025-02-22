<?php

namespace Bnomei\Kart;

use Closure;
use Kirby\Cms\Page;
use Kirby\Cms\Response;
use Kirby\Http\Uri;
use Kirby\Toolkit\A;
use ProductPage;

class Router
{
    const LOGIN = 'kart/login';

    const LOGOUT = 'kart/logout';

    const CART_ADD = 'kart/cart/add';

    const CART_REMOVE = 'kart/cart/remove';

    const CART_CHECKOUT = 'kart/cart/checkout';

    const PROVIDER_SUCCESS = 'kart/cart/success';

    const PROVIDER_CANCEL = 'kart/cart/cancel';

    const WISHLIST_ADD = 'kart/wishlist/add';

    const WISHLIST_REMOVE = 'kart/wishlist/remove';

    const SYNC = 'kart/sync';

    public static function denied(): ?Response
    {
        $middlewares = option('bnomei.kart.middlewares');

        if ($middlewares instanceof Closure) {
            $middlewares = $middlewares();
        }

        if (! is_array($middlewares)) {
            $middlewares = [];
        }

        if ($code = Router::middlewares($middlewares)) {
            return Response::json([], $code);
        }

        return null;
    }

    public static function middlewares(array $middlewares = []): ?int
    {
        foreach ($middlewares as $middleware) {
            [$class, $method] = explode('::', $middleware);
            $code = $class::$method();
            if (! is_null($code)) {
                return $code;
            }
        }

        return null;
    }

    public static function ratelimit(): ?int
    {
        if (! kirby()->option('bnomei.kart.ratelimit.enabled')) {
            return null;
        }

        return Ratelimit::check(kirby()->visitor()->ip()) ? null : 429;
    }

    public static function csrf(): ?int
    {
        if (! kirby()->option('bnomei.kart.csrf.enabled')) {
            return null;
        }

        return csrf(self::get('token')) ? null : 401;
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

    public static function cart_checkout(): string
    {
        return Uri::index()->clone([
            'path' => self::CART_CHECKOUT,
            'query' => static::queryCsrf() + self::encrypt([]),
        ])->toString();
    }

    public static function provider_success(array $params = []): string
    {
        return Uri::index()->clone([
            'path' => self::PROVIDER_SUCCESS,
            'query' => static::queryCsrf() + $params, // not encrypted since it is supposed to be stateless only params
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

    public static function sync(Page|string|null $page): string
    {
        if (! $page) {
            $page = kart()->page(ContentPageEnum::PRODUCTS);
        }

        if ($page instanceof Page) {
            $page = $page->uuid()->id();
        }

        return Uri::index()->clone([
            'path' => self::SYNC,
            'query' => static::queryCsrf() + self::encrypt([
                'page' => $page,
                'user' => kirby()->user()?->id(),
            ]),
        ])->toString();
    }
}
