<?php

use Bnomei\Kart\Router;
use Kirby\Cms\App;
use Kirby\Cms\Response;

return function (App $kirby) {
    return [
        [
            'pattern' => Router::CHECKOUT,
            'method' => 'POST',
            'action' => function () {
                if ($r = Router::denied()) {
                    return $r;
                }

                go(kart()->provider()->checkout());
            },
        ],
        [
            'pattern' => Router::LOGIN,
            'method' => 'POST',
            'action' => function () use ($kirby) {
                if ($r = Router::denied()) {
                    return $r;
                }

                $user = $kirby->user(get('email'));
                if (! $user || ! $user->login(get('password'))) {
                    return Response::json([], 401);
                }

                go(get('redirect', $kirby->site()->url()));
            },
        ],
        [
            'pattern' => Router::LOGOUT,
            'method' => 'POST',
            'action' => function () use ($kirby) {
                if ($r = Router::denied()) {
                    return $r;
                }

                if ($user = $kirby->user()) {
                    $user->logout();
                }

                go(get('redirect', $kirby->site()->url()));
            },
        ],
        [
            'pattern' => '(:all)/'.Router::CART_ADD,
            'method' => 'POST',
            'action' => function ($id) {
                if ($r = Router::denied()) {
                    return $r;
                }

                kart()->cart()->add(
                    page('page://'.get('product'))
                );

                // TODO: add htmx and data-star
                return go($id); // prg
            },
        ],
        [
            'pattern' => '(:all)/'.Router::CART_REMOVE,
            'method' => 'POST',
            'action' => function ($id) {
                if ($r = Router::denied()) {
                    return $r;
                }

                kart()->cart()->remove(
                    page('page://'.get('product'))
                );

                // TODO: add htmx and data-star
                return go($id); // prg
            },
        ],
        [
            'pattern' => '(:all)/'.Router::WISHLIST_ADD,
            'method' => 'POST',
            'action' => function ($id) {
                if ($r = Router::denied()) {
                    return $r;
                }

                kart()->wishlist()->add(
                    page('page://'.get('product'))
                );

                // TODO: add htmx and data-star
                return go($id); // prg
            },
        ],
        [
            'pattern' => '(:all)/'.Router::WISHLIST_REMOVE,
            'method' => 'POST',
            'action' => function ($id) {
                if ($r = Router::denied()) {
                    return $r;
                }

                kart()->wishlist()->remove(
                    page('page://'.get('product'))
                );

                // TODO: add htmx and data-star
                return go($id); // prg
            },
        ],
    ];
};
