<?php

use Bnomei\Kart\Router;
use Kirby\Cms\App;
use Kirby\Cms\Response;

return function (App $kirby) {
    return [
        [
            'pattern' => Router::LOGIN,
            'method' => 'POST',
            'action' => function () use ($kirby) {
                if ($r = Router::denied()) {
                    return $r;
                }

                $email = trim(strip_tags(urldecode(get('email', ''))));
                $user = $kirby->users()
                    ->filterBy('role', 'in', $kirby->option('bnomei.kart.customers.roles'))
                    ->findBy('email', $email);
                if (! $user?->login(get('password'))) {
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
            'pattern' => '(:all)/'.Router::WISHLIST_ADD,
            'method' => 'POST',
            'action' => function ($id) {
                if ($r = Router::denied()) {
                    return $r;
                }

                kart()->wishlist()->add(
                    page('page://'.Router::get('product'))
                );

                // TODO: add htmx and data-star
                go($id); // prg
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
                    page('page://'.Router::get('product'))
                );

                // TODO: add htmx and data-star
                go($id); // prg
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
                    page('page://'.Router::get('product'))
                );

                // TODO: add htmx and data-star
                go($id); // prg
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
                    page('page://'.Router::get('product'))
                );

                // TODO: add htmx and data-star
                go($id); // prg
            },
        ],
        [
            'pattern' => Router::CART_CHECKOUT,
            'method' => 'POST',
            'action' => function () {
                if ($r = Router::denied()) {
                    return $r;
                }

                go(kart()->provider()->checkout());
            },
        ],
        [
            'pattern' => Router::PROVIDER_SUCCESS,
            'method' => 'GET',
            'action' => function () {
                go(kart()->cart()->complete());
            },
        ],
        [
            'pattern' => Router::PROVIDER_CANCEL,
            'method' => 'GET',
            'action' => function () {
                go(kart()->provider()->canceled());
            },
        ],
        [
            'pattern' => Router::SYNC,
            'method' => 'GET',
            'action' => function () {
                if ($r = Router::denied()) {
                    return $r;
                }

                $page = Router::get('page');
                $url = page('page://'.$page)?->panel()->url();

                $from = Router::get('user');
                $user = kirby()->user();
                if (! $user || $user->id() !== $from || $user->role()->name() !== 'admin') {
                    go($url);
                }

                kart()->provider()->sync($page);

                go($url);
            },
        ],
    ];
};
