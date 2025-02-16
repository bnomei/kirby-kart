<?php

namespace Bnomei\Kart;

use Kirby\Cms\Response;
use Kirby\Http\Uri;

class Router
{
    const CHECKOUT = 'kart/checkout';

    const LOGIN = 'kart/login';

    const LOGOUT = 'kart/logout';

    const CART_ADD = '(:any)/kart/cart/add';

    const CART_REMOVE = '(:any)/kart/cart/remove';

    const WISHLIST_ADD = '(:any)/kart/wishlist/add';

    const WISHLIST_REMOVE = '(:any)/kart/wishlist/remove';

    public static function denied(): ?Response
    {
        if (! Router::middleware([
            'csrf',
            'ratelimit',
        ])) {
            return Response::json([], 401);
        }

        return null;
    }

    public static function middleware(array $middleware = []): bool
    {
        $allowed = true;
        foreach ($middleware as $m) {
            $allowed = $allowed && self::$m();
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

        return csrf(kirby()->request()->get('token'));
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

    public static function checkout(): string
    {
        return Uri::index()->clone([
            'path' => self::CHECKOUT,
            'query' => [] + static::queryCsrf(),
        ])->toString();
    }

    public static function login(): string
    {
        return Uri::index()->clone([
            'path' => self::LOGIN,
            'query' => [] + static::queryCsrf(),
        ])->toString();
    }

    public static function logout(): string
    {
        return Uri::index()->clone([
            'path' => self::LOGOUT,
            'query' => [] + static::queryCsrf(),
        ])->toString();
    }

    public static function cart_add(Product $product): string
    {
        return Uri::index()->clone([
            'path' => str_replace('(:any)/', '', self::CART_ADD),
            'query' => [
                'product' => $product->id(),
            ] + static::queryCsrf(),
        ])->toString();
    }

    public static function cart_remove(Product $product): string
    {
        return Uri::index()->clone([
            'path' => str_replace('(:any)/', '', self::CART_REMOVE),
            'query' => [
                'product' => $product->id(),
            ] + static::queryCsrf(),
        ])->toString();
    }

    public static function wishlist_add(Product $product): string
    {
        return Uri::index()->clone([
            'path' => str_replace('(:any)/', '', self::WISHLIST_ADD),
            'query' => [
                'product' => $product->id(),
            ] + static::queryCsrf(),
        ])->toString();
    }

    public static function wishlist_remove(Product $product): string
    {
        return Uri::index()->clone([
            'path' => str_replace('(:any)/', '', self::WISHLIST_REMOVE),
            'query' => [
                'product' => $product->id(),
            ] + static::queryCsrf(),
        ])->toString();
    }
}
