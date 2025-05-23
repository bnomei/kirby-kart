<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Bnomei\Kart\Mixins\Captcha;
use Bnomei\Kart\Mixins\Turnstile;
use Closure;
use Kirby\Cms\Page;
use Kirby\Cms\Response;
use Kirby\Http\Uri;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use ProductPage;

class Router
{
    use Captcha;
    use Turnstile;

    const ACCOUNT = 'account';

    const ACCOUNT_DELETE = 'kart/account-delete';

    const CAPTCHA = 'kart/captcha';

    const CART = 'cart';

    const CART_ADD = 'kart/cart-add';

    const CART_BUY = 'kart/cart-buy';

    const CART_CHECKOUT = 'kart/cart-checkout';

    const CART_LATER = 'kart/cart-later';

    const CART_REMOVE = 'kart/cart-remove';

    const CART_SET_AMOUNT = 'kart/cart-set-amount';

    const CSRF = 'kart/csrf';

    const ENCRYPTED_QUERY = 'keq'; // make it less likely to collide with others

    const KART = 'kart';

    const LICENSES_ACTIVATE = 'kart/licenses/activate';

    const LICENSES_DEACTIVATE = 'kart/licenses/deactivate';

    const LICENSES_VALIDATE = 'kart/licenses/validate';

    const LOGIN = 'kart/login';

    const LOGOUT = 'kart/logout';

    const MAGIC_LINK = 'kart/magic-link';

    const PROVIDER_CANCEL = 'kart/provider-cancel';

    const PROVIDER_PAYMENT = 'kart/provider-payment';

    const PROVIDER_PORTAL = 'kart/provider-portal';

    const PROVIDER_SUCCESS = 'kart/provider-success';

    const PROVIDER_SYNC = 'kart/provider-sync';

    const SIGNUP_MAGIC = 'kart/signup';

    const WISHLIST_ADD = 'kart/wishlist-add';

    const WISHLIST_NOW = 'kart/wishlist-now';

    const WISHLIST_REMOVE = 'kart/wishlist-remove';

    public static function denied(array $check = [], bool $exclusive = false): ?Response
    {
        $middlewares = kart()->option('middlewares.enabled');

        if ($middlewares instanceof Closure) {
            $middlewares = $middlewares();
        }

        if (! is_array($middlewares)) {
            $middlewares = [];
        }

        $middlewares = $exclusive ? $check : array_merge($middlewares, $check);

        if ($code = Router::middlewares($middlewares)) {
            return Router::go(Router::back(), code: $code);
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
            [$class, $method] = explode('::', (string) $middleware);
            $code = $class::$method();
            if (! is_null($code)) {
                return $code;
            }
        }

        return null;
    }

    public static function go(
        ?string $url = null,
        null|string|array $json = null,
        ?string $html = null,
        ?int $code = null,
    ): ?Response {
        $mode = kart()->option('router.mode');

        $code ??= 200;

        if (kirby()->request()->header(strval(kart()->option('router.header.htmx')))) {
            $mode = 'htmx';
        }

        if (! is_null($html) || kirby()->request()->header('Accept') === 'application/html') {
            $mode = 'html';
        }

        if (! is_null($json) || kirby()->request()->header('Accept') === 'application/json') {
            $mode = 'json';
        }

        if ($mode === 'go') {
            $url = strval($url ?? Router::get('redirect', '/'));
            Response::go($url, $code); // NOTE: code provided but a redirect will always be a 302, use the JSON API if you need the code
            // NOTE: this does not redirect
            // header('Location: ' . $url, true, $code);
            // exit;
        }

        if ($mode === 'json') {
            if ($code < 300 && empty($json)) {
                // the snippet could also set a header with a different code, echo and die itself
                // instead of just returning a string and defaulting to the 200 status code below
                if ($snippet = Router::getSnippet()) {
                    $json = strval(snippet(
                        $snippet, // NOTE: snippet(null|unknown) yields ''
                        data: array_merge(kirby()->request()->data(), Router::resolveModelsFromRequest()),
                        return: true
                    ));
                }
            }
            /*
            if (is_string($json)) {
                $json = json_decode($json, true);
                $json['token'] = csrf(); // return a new token for the next request
            }
            */

            return Response::json($json ?? [], $code);
        }

        if (in_array($mode, ['html', 'htmx'])) {
            if ($code) {
                header('HTTP/1.1 '.$code);
            }
            if ($code < 300) {
                if ($snippet = Router::getSnippet()) {
                    echo ! empty($html) ? $html : strval(snippet(
                        $snippet, // NOTE: snippet(null|unknown) yields ''
                        data: array_merge(kirby()->request()->data(), Router::resolveModelsFromRequest()),
                        return: true
                    ));
                }
            }

            exit;
        }

        return null;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $request = kirby()->request();
        $value = $request->get($key, $default);

        // if it has value encrypted, then prefer that
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

        return Kart::decrypt($props, kart()->option('router.salt'), true); // @phpstan-ignore-line
    }

    public static function getSnippet(?string $path = null): ?string
    {
        $path ??= strval(Router::get('snippet', kirby()->request()->path()->toString()));
        $path = '/'.$path; // avoid matching /some-[not-value] at /not-value
        $map = (array) kart()->option('router.snippets', []);
        foreach ($map as $key => $value) {
            if (is_numeric($key)) {
                $key = $value;
            }
            if (is_string($key) && is_string($value) && Str::endsWith($path, '/'.$key)) {
                return $value;
            }
        }

        return null;
    }

    public static function resolveModelsFromRequest(): array
    {
        $models = [
            'page' => null,
            'product' => null,
            'user' => kirby()->user(),
            'site' => kirby()->site(),
            'kirby' => kirby(),
        ];
        foreach ($models as $key => $value) {
            $value = self::get($key); // might be encrypted
            if (empty($value) || ! is_string($value)) {
                continue;
            }

            switch ($key) {
                case 'page':
                    $models['page'] = kirby()->page('page://'.$value);
                    break;
                case 'product':
                    $models['product'] = kirby()->page('page://'.$value);
                    break;
                case 'user':
                    $models['user'] ??= kirby()->user($value);
                    break;
                default: break;
            }
        }

        return $models;
    }

    public static function back(): ?string
    {
        return kirby()->request()->header('referer');
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
        if (A::has((array) kirby()->option('auth.methods', []), 'kart-magic-link') === false) {
            return 405;
        }

        return null;
    }

    public static function hasRatelimit(): ?int
    {
        if (! kart()->option('middlewares.ratelimit.enabled')) {
            return null;
        }

        return Ratelimit::check(kirby()->visitor()->ip()) ? null : 429;
    }

    public static function hasCsrf(): ?int
    {
        // form data field name or false/null
        $name = kart()->option('middlewares.csrf');
        if (! is_string($name)) {
            return null;
        }

        $token = self::get($name);

        // prefer from header if it exists
        $token = kirby()->request()->header(
            strval(kart()->option('router.header.csrf')),
            $token
        );

        return is_string($token) && csrf($token) ? null : 401;
    }

    public static function hasBlacklist(): ?int
    {
        $blacklist = kart()->option('middlewares.blacklist');
        if (option('under-attack', false) !== false ||
            ($blacklist === 'under-attack') ||
            (is_array($blacklist) && in_array('under-attack', $blacklist))
        ) {
            return 403;
        }
        if (! is_array($blacklist)) {
            $blacklist = [];
        }
        if (in_array(kirby()->request()->path(), $blacklist)) {
            return 403;
        }

        return null;
    }

    public static function account(): string
    {
        return url(self::ACCOUNT);
    }

    public static function account_delete(): string
    {
        return self::factory(self::ACCOUNT_DELETE);
    }

    public static function factory(string $path, array $query = [], array $params = []): string
    {
        return Uri::index()->clone([
            'path' => $path,
            'query' => array_merge(static::queryCsrf(), self::encrypt(array_merge(self::modelsFromRequest(), $query)), $params),
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
        $password = kart()->option('router.salt');
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

    public static function modelsFromRequest(): array
    {
        return [
            'page' => page(kirby()->request()->path()->toString())?->uuid()->id(),
        ];
    }

    public static function login(?string $email = null): string
    {
        return self::factory(self::LOGIN, params: array_filter([
            'email' => $email,
        ]));
    }

    public static function licenses_activate(?string $license_key = null): string
    {
        return self::factory(self::LICENSES_ACTIVATE, query: array_filter([
            'license_key' => $license_key,
        ]));
    }

    public static function licenses_deactivate(?string $license_key = null): string
    {
        return self::factory(self::LICENSES_DEACTIVATE, query: array_filter([
            'license_key' => $license_key,
        ]));
    }

    public static function licenses_validate(?string $license_key = null): string
    {
        return self::factory(self::LICENSES_VALIDATE, query: array_filter([
            'license_key' => $license_key,
        ]));
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

    public static function provider_payment(array $params = []): string
    {
        return self::factory(
            self::PROVIDER_PAYMENT,
            [], // no encypted values for payment provider needed
            array_merge([
                'success_url' => url(Router::PROVIDER_SUCCESS).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => url(Router::PROVIDER_CANCEL),
            ], $params)
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

    public static function cart_set_amount(ProductPage $product): string
    {
        return self::factory(
            self::current().'/'.self::CART_SET_AMOUNT,
            [
                'product' => $product->uuid()->id(),
            ]
        );
    }

    public static function current(): string
    {
        return kirby()->request()->path().'/'.kirby()->request()->params();
    }

    public static function idWithParams(string $pattern): string
    {
        return str_replace('/'.$pattern, '', kirby()->request()->path().'/'.kirby()->request()->params());
    }

    public static function cart(): string
    {
        return url(self::CART);
    }

    public static function kart(): string
    {
        return url(self::KART);
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
        return url(self::CSRF);
    }

    public static function captcha(): string
    {
        return url(self::CAPTCHA);
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
