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

    const CSRF_TOKEN = 'kart/csrf';

    const ENCRYPTED_QUERY = 'keq'; // make it less likely to collide with others

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
        if (! kirby()->environment()->isLocal() && kirby()->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
            return null;
        }

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

        $token = self::get('token');

        return is_string($token) && csrf($token) ? null : 401;
    }

    public static function go(
        ?string $url = null,
        string|array $json = [],
        ?string $html = null,
        ?int $code = null,
    ): ?Response {
        $mode = kirby()->option('bnomei.kart.router.mode');

        if ($mode === 'go') {
            $url = strval(Router::get('redirect', $url ?? '/'));
            // Response::go($url, $code ?? 302);
        }

        if ($mode === 'json') {
            $json = strval($json ?? []);

            return Response::json($json, $code ?? 200);
        }

        if ($mode === 'html') {
            if ($code) {
                header('HTTP/1.1 '.$code.' '.$http_response_header[0]);
            }
            echo $html ?? snippet(
                Router::get('snippet', ''),
                data: kirby()->request()->data(),
                return: true
            );
            exit;
        }

        return null;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $request = kirby()->request();
        if ($q = $request->get(self::ENCRYPTED_QUERY)) {
            $result = self::decrypt($q);

            return is_array($result) ? A::get($result, $key, $default) : $request->get($key, $default);
        }

        return $request->get($key, $default);
    }

    public static function decrypt(string $props): mixed
    {
        return Helper::decrypt($props, option('bnomei.kart.router.encryption'), true); // @phpstan-ignore-line
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
            self::ENCRYPTED_QUERY => Helper::encrypt($query, $password, true),
        ];
    }

    public static function current(): string
    {
        return kirby()->request()->path();
    }

    public static function factory(string $path, array $query = [], array $params = []): string
    {
        return Uri::index()->clone([
            'path' => $path,
            'query' => static::queryCsrf() + self::encrypt($query) + $params,
        ])->toString();
    }

    public static function login(): string
    {
        return self::factory(self::LOGIN);
    }

    public static function logout(): string
    {
        return self::factory(self::LOGOUT);
    }

    public static function cart_checkout(): string
    {
        return self::factory(self::CART_CHECKOUT);
    }

    public static function provider_success(array $params = []): string
    {
        return self::factory(
            self::PROVIDER_SUCCESS,
            params: $params // not encrypted since it is supposed to be stateless only params
        );
    }

    public static function cart_add(ProductPage $product): string
    {
        return self::factory(
            self::current().'/'.self::CART_ADD,
            [
                'product' => $product->uuid()->id(),
            ]
        );
    }

    public static function cart_remove(ProductPage $product): string
    {
        return self::factory(
            self::current().'/'.self::CART_REMOVE,
            [
                'product' => $product->uuid()->id(),
            ]
        );
    }

    public static function wishlist_add(ProductPage $product): string
    {
        return self::factory(
            self::current().'/'.self::WISHLIST_ADD,
            [
                'product' => $product->uuid()->id(),
            ]
        );
    }

    public static function wishlist_remove(ProductPage $product): string
    {
        return self::factory(
            self::current().'/'.self::WISHLIST_REMOVE,
            [
                'product' => $product->uuid()->id(),
            ]
        );
    }

    public static function sync(Page|string|null $page): string
    {
        if (! $page) {
            $page = kart()->page(ContentPageEnum::PRODUCTS);
        }

        if ($page instanceof Page) {
            $page = $page->uuid()->id();
        }

        return self::factory(
            self::SYNC,
            [
                'page' => $page,
                'user' => kirby()->user()?->id(),
            ]
        );
    }
}
