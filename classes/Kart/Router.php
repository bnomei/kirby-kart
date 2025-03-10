<?php

namespace Bnomei\Kart;

use Bnomei\Kart\Mixins\Captcha;
use Bnomei\Kart\Mixins\Turnstile;
use Closure;
use Kirby\Cms\Page;
use Kirby\Cms\Response;
use Kirby\Http\Uri;
use Kirby\Toolkit\A;
use ProductPage;

class Router
{
    use Captcha;
    use Turnstile;

    const ACCOUNT_DELETE = 'kart/account/delete';

    const CAPTCHA = 'kart/captcha';

    const CART = 'cart';

    const CART_ADD = 'kart/cart/add';

    const CART_BUY = 'kart/cart/buy';

    const CART_CHECKOUT = 'kart/cart/checkout';

    const CART_LATER = 'kart/cart/later';

    const CART_REMOVE = 'kart/cart/remove';

    const CSRF = 'kart/csrf';

    const ENCRYPTED_QUERY = 'keq'; // make it less likely to collide with others

    const KART = 'kart';

    const LOGIN = 'kart/login';

    const LOGOUT = 'kart/logout';

    const MAGIC_LINK = 'kart/magic-link';

    const PROVIDER_CANCEL = 'kart/provider/cancel';

    const PROVIDER_PAYMENT = 'kart/provider/payment';

    const PROVIDER_PORTAL = 'kart/provider/portal';

    const PROVIDER_SUCCESS = 'kart/provider/success';

    const PROVIDER_SYNC = 'kart/provider/sync';

    const SIGNUP_MAGIC = 'kart/signup';

    const WISHLIST_ADD = 'kart/wishlist/add';

    const WISHLIST_NOW = 'kart/wishlist/now';

    const WISHLIST_REMOVE = 'kart/wishlist/remove';

    public static function denied(array $check = [], bool $exclusive = false): ?Response
    {
        $middlewares = kart()->option('middlewares.enabled');

        if ($middlewares instanceof Closure) {
            $middlewares = $middlewares();
        }

        if (! is_array($middlewares)) {
            $middlewares = [];
        }

        $middlewares = $exclusive ? $check : $middlewares + $check;

        if ($code = Router::middlewares($middlewares)) {
            return Response::json([], $code);
        }

        return null;
    }

    public static function middlewares(array $middlewares = []): ?int
    {
        if (count($middlewares) === 0) {
            return null;
        }

        if (! kirby()->environment()->isLocal() && kirby()->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
            return null;
        }

        foreach ($middlewares as $middleware) {
            if ($middleware instanceof Closure) {
                return $middleware();
            }
            [$class, $method] = explode('::', $middleware);
            $code = $class::$method();
            if (! is_null($code)) {
                return $code;
            }
        }

        return null;
    }

    public static function hasUser(): ?int
    {
        $user = kirby()->user();
        if (! $user) {
            return 401;
        }

        return null;
    }

    public static function hasAdmin(): ?int
    {
        $user = kirby()->user();
        if (! $user || $user->isAdmin() === false) {
            return 401;
        }

        return null;
    }

    public static function hasMagicLink(): ?int
    {
        if (A::has(kirby()->option('auth.methods', []), 'kart-magic-link') === false) {
            return 405;
        }

        return null;
    }

    public static function hasRatelimit(): ?int
    {
        if (! kart()->option('ratelimit.enabled')) {
            return null;
        }

        return Ratelimit::check(kirby()->visitor()->ip()) ? null : 429;
    }

    public static function hasCsrf(): ?int
    {
        if (! kart()->option('csrf.enabled')) {
            return null;
        }

        $token = self::get('token');

        return is_string($token) && csrf($token) ? null : 401;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $request = kirby()->request();
        $value = $request->get($key, $default);

        $decrypted = self::decrypt($request->get(self::ENCRYPTED_QUERY));
        if (is_array($decrypted)) {
            $value = A::get($decrypted, $key, $value);
        }

        return $value;
    }

    public static function decrypt(?string $props = null): mixed
    {
        if (empty($props)) {
            return null;
        }

        return Kart::decrypt($props, kart()->option('router.encryption'), true); // @phpstan-ignore-line
    }

    public static function go(
        ?string $url = null,
        string|array $json = [],
        ?string $html = null,
        ?int $code = null,
    ): ?Response {
        $mode = kart()->option('router.mode');

        if ($mode === 'go') {
            $url = strval(Router::get('redirect', $url ?? '/'));
            Response::go($url, $code ?? 302);
        }

        if ($mode === 'json') {
            if (empty($json)) {
                // the snippet could also set a header with a different code, echo and die itself
                // instead of just returning a string and defaulting to the 200 status code below
                $json = snippet(
                    Router::get('snippet'), // NOTE: snippet(null) yields ''
                    data: kirby()->request()->data(),
                    return: true
                );
            }
            if (is_string($json)) {
                $json = json_decode($json, true);
                $json['token'] = csrf(); // return a new token for the next request
            }

            return Response::json($json, $code ?? 200);
        }

        if ($mode === 'html') {
            if ($code) {
                header('HTTP/1.1 '.$code.' '.$http_response_header[0]);
            }
            echo $html ?? snippet(
                Router::get('snippet'), // NOTE: snippet(null) yields ''
                data: kirby()->request()->data(),
                return: true
            );
            exit;
        }

        return null;
    }

    public static function login(?string $email = null): string
    {
        return self::factory(self::LOGIN, params: array_filter([
            'email' => $email,
        ]));
    }

    public static function factory(string $path, array $query = [], array $params = []): string
    {
        return Uri::index()->clone([
            'path' => $path,
            'query' => static::queryCsrf() + self::encrypt($query) + $params,
        ])->toString();
    }

    protected static function queryCsrf(): array
    {
        $csrf = kart()->option('router.csrf');
        if (! $csrf) {
            return [];
        }

        return [
            strval($csrf) => csrf(),
        ];
    }

    public static function encrypt(array $query): array
    {
        $password = kart()->option('router.encryption');
        if ($password instanceof Closure) {
            $password = $password();
        }

        if (! $password) {
            return $query;
        }

        return [
            self::ENCRYPTED_QUERY => Kart::encrypt($query, $password, true),
        ];
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

    public static function provider_payment(array $query = []): string
    {
        return self::factory(
            self::PROVIDER_PAYMENT,
            array_merge([
                'success_url' => url(Router::PROVIDER_SUCCESS).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => url(Router::PROVIDER_CANCEL),
            ], $query)
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

    public static function current(): string
    {
        return kirby()->request()->path();
    }

    public static function cart(): string
    {
        return self::factory(self::current().'/'.self::CART);
    }

    public static function kart(): string
    {
        return self::factory(self::KART);
    }

    public static function cart_buy(ProductPage $product): string
    {
        return self::factory(
            self::current().'/'.self::CART_BUY,
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

    public static function cart_later(ProductPage $product): string
    {
        return self::factory(
            self::current().'/'.self::CART_LATER,
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

    public static function wishlist_now(ProductPage $product): string
    {
        return self::factory(
            self::current().'/'.self::WISHLIST_NOW,
            [
                'product' => $product->uuid()->id(),
            ]
        );
    }

    public static function csrf(): string
    {
        return self::factory(self::CSRF);
    }

    public static function captcha(): string
    {
        return self::factory(self::CAPTCHA);
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
            self::PROVIDER_SYNC,
            [
                'page' => $page,
                'user' => kirby()->user()?->id(),
            ]
        );
    }

    public static function magiclink(?string $email = null): string
    {
        return self::factory(self::MAGIC_LINK, params: array_filter([
            'email' => $email,
        ]));
    }

    public static function signup_magic(?string $email = null): string
    {
        return self::factory(self::SIGNUP_MAGIC, params: array_filter([
            'email' => $email,
        ]));
    }
}
