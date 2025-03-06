<?php

use Bnomei\Kart\Router;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Response;

return function (App $kirby) {
    return [
        [
            'pattern' => Router::CSRF_TOKEN,
            'method' => 'GET',
            'action' => function () use ($kirby) {
                return Response::json([
                    'token' => $kirby->csrf(),
                ], 201);
            },
        ],
        [
            'pattern' => Router::LOGIN,
            'method' => 'POST',
            'action' => function () use ($kirby) {
                if ($r = Router::denied()) {
                    return $r;
                }

                if (! $kirby->environment()->isLocal() && $kirby->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
                    return Response::json([], 451);
                }

                $email = trim(strip_tags(urldecode(get('email', ''))));
                $user = $kirby->users()
                    ->filterBy('role', 'in', $kirby->option('bnomei.kart.customers.roles'))
                    ->findBy('email', $email);
                if (! $user?->login(get('password'))) {
                    return Response::json([], 401);
                }

                return Router::go();
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

                return Router::go();
            },
        ],
        [
            'pattern' => [
                Router::WISHLIST_ADD,
                '(:all)/'.Router::WISHLIST_ADD,
            ],
            'method' => 'POST',
            'action' => function (?string $id = null) {
                if ($r = Router::denied()) {
                    return $r;
                }

                kart()->wishlist()->add(
                    page('page://'.Router::get('product'))
                );

                return Router::go($id);
            },
        ],
        [
            'pattern' => [
                Router::WISHLIST_REMOVE,
                '(:all)/'.Router::WISHLIST_REMOVE,
            ],
            'method' => 'POST',
            'action' => function (?string $id = null) {
                if ($r = Router::denied()) {
                    return $r;
                }

                kart()->wishlist()->remove(
                    page('page://'.Router::get('product')),
                    999
                );

                return Router::go($id);
            },
        ],
        [
            'pattern' => [
                Router::WISHLIST_NOW,
                '(:all)/'.Router::WISHLIST_NOW,
            ],
            'method' => 'POST',
            'action' => function (?string $id = null) {
                if ($r = Router::denied()) {
                    return $r;
                }

                kart()->cart()->add(
                    page('page://'.Router::get('product'))
                );
                kart()->wishlist()->remove(
                    page('page://'.Router::get('product')),
                    999
                );

                return Router::go($id);
            },
        ],
        [
            'pattern' => [
                Router::CART_ADD,
                '(:all)/'.Router::CART_ADD,
            ],
            'method' => 'POST',
            'action' => function (?string $id = null) {
                if ($r = Router::denied()) {
                    return $r;
                }

                kart()->cart()->add(
                    page('page://'.Router::get('product'))
                );

                return Router::go($id);
            },
        ],
        [
            'pattern' => [
                Router::CART_BUY,
                '(:all)/'.Router::CART_BUY,
            ],
            'method' => 'POST',
            'action' => function (?string $id = null) {
                if ($r = Router::denied()) {
                    return $r;
                }

                kart()->cart()->add(
                    page('page://'.Router::get('product'))
                );

                if (! kart()->canCheckout()) {
                    Router::go($id);
                }

                Response::go(kart()->provider()->checkout());
            },
        ],
        [
            'pattern' => [
                Router::CART_REMOVE,
                '(:all)/'.Router::CART_REMOVE,
            ],
            'method' => 'POST',
            'action' => function (?string $id = null) {
                if ($r = Router::denied()) {
                    return $r;
                }

                kart()->cart()->remove(
                    page('page://'.Router::get('product'))
                );

                return Router::go($id);
            },
        ],
        [
            'pattern' => Router::CART_CHECKOUT,
            'method' => 'POST',
            'action' => function () {
                if ($r = Router::denied()) {
                    return $r;
                }

                if (! kart()->canCheckout()) {
                    Response::go('/');
                }

                Response::go(kart()->provider()->checkout());
            },
        ],
        [
            'pattern' => [
                Router::CART_LATER,
                '(:all)/'.Router::CART_LATER,
            ],
            'method' => 'POST',
            'action' => function (?string $id = null) {
                if ($r = Router::denied()) {
                    return $r;
                }

                kart()->cart()->remove(
                    page('page://'.Router::get('product')),
                    999 // aka all
                );
                kart()->wishlist()->add(
                    page('page://'.Router::get('product'))
                );

                return Router::go($id);
            },
        ],
        [
            'pattern' => Router::PROVIDER_SUCCESS,
            'method' => 'GET|POST',
            'action' => function () {
                Response::go(kart()->cart()->complete());
            },
        ],
        [
            'pattern' => Router::PROVIDER_CANCEL,
            'method' => 'GET',
            'action' => function () {
                Response::go(kart()->provider()->canceled());
            },
        ],
        [
            'pattern' => Router::PROVIDER_PAYMENT,
            'method' => 'GET|POST',
            'action' => function () {
                $payment = new Page([
                    'id' => Router::PROVIDER_PAYMENT,
                    'slug' => 'payment',
                    'template' => 'payment',
                    'model' => 'payment',
                    'content' => [
                        'title' => t('bnomei.kart.payment', 'Payment'),
                    ],
                ]);

                return site()->visit($payment);
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

                Response::go($url);
            },
        ],
    ];
};
