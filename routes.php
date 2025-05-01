<?php

use Bnomei\Kart\Kart;
use Bnomei\Kart\MagicLinkChallenge;
use Bnomei\Kart\Router;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Response;
use Kirby\Cms\User;
use Kirby\Toolkit\A;

return function (App $kirby) {
    return [
        [
            'pattern' => Router::CSRF,
            'method' => 'GET',
            'action' => function () use ($kirby) {
                return Response::json([
                    'token' => $kirby->csrf(),
                ], 201);
            },
        ],
        [
            'pattern' => Router::CAPTCHA,
            'method' => 'GET',
            'action' => function () {
                if ($r = Router::denied([
                    Router::class.'::hasRatelimit',
                ], exclusive: true)) {
                    return $r;
                }

                header('Content-Type: image/jpeg');
                header('Cache-Control: no-cache, no-store, must-revalidate'); // bust caches
                kart()->option('captcha.set')(inline: false);
                exit();
            },
        ],
        [
            'pattern' => Router::CAPTCHA,
            'method' => 'POST',
            'action' => function () {
                if ($r = Router::denied([
                    Router::class.'::hasRatelimit',
                ], exclusive: true)) {
                    return $r;
                }

                return Response::json()(kart()->option('captcha.set')());
            },
        ],
        [
            'pattern' => Router::LICENSES_VALIDATE,
            'method' => 'POST',
            'action' => function () {
                if ($r = Router::denied([
                    Router::class.'::hasRatelimit',
                ], exclusive: true)) {
                    return $r;
                }

                return Response::json()(kart()->licenses()->validate(
                    substr(trim(strip_tags(strval(get('license_key', '')))), 0, 36),
                ));
            },
        ],
        [
            'pattern' => Router::LOGIN,
            'method' => 'GET',
            'action' => function () {

                $page = kirby()->page(Router::LOGIN) ?? new Page([
                    'slug' => 'login',
                    'template' => 'login',
                    'content' => [
                        'title' => t('bnomei.kart.login'),
                    ],
                ]);

                return site()->visit($page);
            },
        ],
        [
            'pattern' => Router::LOGIN,
            'method' => 'POST',
            'action' => function () use ($kirby) {
                if ($r = Router::denied([
                    Router::class.'::hasCaptcha',
                    Router::class.'::hasTurnstile',
                ])) {
                    return $r;
                }

                if (! $kirby->environment()->isLocal() && $kirby->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
                    return Response::json([], 451);
                }

                $email = trim(strip_tags(urldecode(get('email', ''))));
                // TODO: performance on a lot of users might drop
                $user = $kirby->users()
                    ->customers()
                    ->findBy('email', $email);

                $pw = get('password');
                if ($pw && strlen($pw) >= 8 && $user?->login($pw)) {
                    return Router::go();
                }

                return Router::go(Router::back(), code: 401);
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
            'pattern' => Router::SIGNUP_MAGIC,
            'method' => 'GET',
            'action' => function () {

                $page = kirby()->page(Router::SIGNUP_MAGIC) ?? new Page([
                    'slug' => 'signup',
                    'template' => 'signup',
                    'content' => [
                        'title' => t('bnomei.kart.signup'),
                    ],
                ]);

                return site()->visit($page);
            },
        ],
        [
            'pattern' => Router::SIGNUP_MAGIC,
            'method' => 'POST',
            'action' => function () {
                if ($r = Router::denied([
                    Router::class.'::hasCaptcha',
                    Router::class.'::hasTurnstile',
                ])) {
                    return $r;
                }

                $data = Kart::sanitize(kirby()->request()->data());

                // create a virtual user to make kirby happily create
                // the magic challenge even if NO user with that
                // email exists.
                $maybe = kirby()->user(A::get($data, 'email'));
                if ($maybe) {
                    kart()->message(t('error.user.duplicate'));
                }
                $user = new User([
                    'email' => A::get($data, 'email'),
                    'name' => A::get($data, 'name'),
                    'language' => kirby()->language()?->code(),
                    'role' => kart()->option('customers.roles')[0],
                ]);
                if ($user && is_null($maybe)) { // @phpstan-ignore-line
                    $code = MagicLinkChallenge::create($user, [
                        'mode' => 'login',
                        'timeout' => 10 * 60,
                        'email' => A::get($data, 'email'),
                        'name' => A::get($data, 'name'),
                        'signup' => 1,
                        'success_url' => A::get($data, 'success_url'),
                    ]);
                    if ($code) {
                        kirby()->session()->set('kirby.challenge.type', 'login');
                        kirby()->session()->set('kirby.challenge.code', password_hash($code, PASSWORD_DEFAULT));

                        return Router::go();
                    }
                }

                return Router::go(Router::back(), code: 401);
            },
        ],
        [
            'pattern' => Router::MAGIC_LINK,
            'method' => 'POST',
            'action' => function () {
                if ($r = Router::denied([
                    Router::class.'::hasCaptcha',
                    Router::class.'::hasTurnstile',
                ])) {
                    return $r;
                }

                $data = Kart::sanitize(kirby()->request()->data());

                $user = kirby()->user(A::get($data, 'email'));
                if (! $user) {
                    kart()->message(t('error.user.notFound'));
                }
                if ($user && $user->isCustomer()) {
                    $code = MagicLinkChallenge::create($user, [
                        'mode' => 'login-magic',
                        'timeout' => 10 * 60,
                        'email' => A::get($data, 'email'),
                        'success_url' => A::get($data, 'success_url'),
                    ]);
                    if ($code) {
                        kirby()->session()->set('kirby.challenge.type', 'login');
                        kirby()->session()->set('kirby.challenge.code', password_hash($code, PASSWORD_DEFAULT));

                        return Router::go();
                    }
                }

                return Router::go(Router::back(), code: 401);
            },
        ],
        [
            'pattern' => Router::MAGIC_LINK,
            'method' => 'GET',
            'action' => function () {
                if ($r = Router::denied([
                    Router::class.'::hasMagicLink',
                ], true)) {
                    return $r;
                }

                if (Kart::checkSignature(get('signature'), kirby()->request()->url()) === false) {
                    return Response::json([], 401);
                }

                if (get('prg') !== '1') {
                    $url = kirby()->request()->url().'&prg=1';
                    header('Refresh: 1; url='.$url);
                    exit();
                }

                $data = Kart::sanitize(kirby()->request()->data());

                $code = A::get($data, 'code');

                $user = null;
                if (get('signup')) {
                    // try creating
                    $user = kart()->createOrUpdateCustomer([
                        'customer' => [
                            'email' => A::get($data, 'email'),
                            'name' => A::get($data, 'name'),
                        ],
                    ], false); // do not trigger created since signup below will be used

                    kirby()->trigger('kart.user.signup', ['user' => $user]);
                }
                if (! $user) {
                    // if not created because it exists, then try finding
                    $user = kirby()->user(A::get($data, 'email'));
                }

                if (! $user) {
                    kart()->message(t('error.user.notFound'));
                }

                if ($user && MagicLinkChallenge::verify($user, $code)) {

                    kirby()->session()->remove('kirby.challenge.code');
                    $user->loginPasswordless();
                }

                return Router::go();
            },
        ],
        [
            'pattern' => Router::PROVIDER_PORTAL,
            'method' => 'POST',
            'action' => function () {
                if ($r = Router::denied([
                    Router::class.'::hasUser',
                ])) {
                    return $r;
                }

                return Router::go(kart()->provider()->portal(
                    Router::get('redirect', site()->url())
                ));
            },
        ],
        [
            'pattern' => Router::ACCOUNT_DELETE,
            'method' => 'POST',
            'action' => function () {
                if ($r = Router::denied([
                    Router::class.'::hasUser',
                ])) {
                    return $r;
                }

                $user = kirby()->user();
                if ($user?->isAdmin()) {
                    return Router::go(Router::back(), code: 401);
                }

                $user?->logout();
                $user?->softDelete();
                kart()->message(t('bnomei.kart.goodbye'));

                go(site()->url());
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

                /** @var ProductPage|null $page */
                $page = page('page://'.Router::get('product'));
                $variant = $page?->variantFromRequestData(kirby()->request()->data());
                kart()->wishlist()->add(
                    $page,
                    variant: $variant
                );
                kart()->wishlist()->save();

                return Router::go(Router::idWithParams(Router::WISHLIST_ADD));
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

                /** @var ProductPage|null $page */
                $page = page('page://'.Router::get('product'));
                $variant = $page?->variantFromRequestData(kirby()->request()->data());
                kart()->wishlist()->remove(
                    $page,
                    999,
                    variant: $variant
                );
                kart()->wishlist()->save();

                return Router::go(Router::idWithParams(Router::WISHLIST_REMOVE));
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

                /** @var ProductPage|null $page */
                $page = page('page://'.Router::get('product'));
                $variant = $page?->variantFromRequestData(kirby()->request()->data());
                kart()->cart()->add(
                    $page,
                    variant: $variant
                );
                kart()->cart()->save();
                kart()->wishlist()->remove(
                    $page,
                    999,
                    variant: $variant
                );
                kart()->wishlist()->save();

                return Router::go(Router::idWithParams(Router::WISHLIST_NOW));
            },
        ],
        [
            'pattern' => Router::KART,
            'method' => 'GET',
            'action' => function () {
                kart()->tmnt(); // create demo content if needed

                $page = new Page([
                    'slug' => 'kart',
                    'template' => 'kart',
                    'content' => [
                        'title' => t('bnomei.kart.kart'),
                    ],
                ]);

                return site()->visit($page);
            },
        ],
        [
            'pattern' => Router::CART,
            'method' => 'GET',
            'action' => function () {
                $page = kirby()->page(Router::CART) ?? new Page([
                    'slug' => 'cart',
                    'template' => 'cart',
                    'content' => [
                        'title' => t('bnomei.kart.cart'),
                    ],
                ]);

                return site()->visit($page);
            },
        ],
        [
            'pattern' => [
                Router::CART_SET_AMOUNT,
                '(:all)/'.Router::CART_SET_AMOUNT,
            ],
            'method' => 'POST',
            'action' => function (?string $id = null) {
                if ($r = Router::denied()) {
                    return $r;
                }

                /** @var ProductPage|null $page */
                $page = page('page://'.Router::get('product'));
                $variant = $page?->variantFromRequestData(kirby()->request()->data());
                kart()->cart()->add(
                    $page,
                    max(0, intval(Router::get('amount', 1))),
                    true,
                    variant: $variant
                );
                kart()->cart()->save();

                return Router::go(Router::idWithParams(Router::CART_SET_AMOUNT));
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

                /** @var ProductPage|null $page */
                $page = page('page://'.Router::get('product'));
                $variant = $page?->variantFromRequestData(kirby()->request()->data());
                kart()->cart()->add(
                    $page,
                    max(1, intval(Router::get('amount', 1))),
                    variant: $variant
                );
                kart()->cart()->save();

                return Router::go(Router::idWithParams(Router::CART_ADD));
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

                /** @var ProductPage|null $page */
                $page = page('page://'.Router::get('product'));
                $variant = $page?->variantFromRequestData(kirby()->request()->data());
                kart()->cart()->add(
                    $page,
                    max(1, intval(Router::get('amount', 1))),
                    variant: $variant
                );
                kart()->cart()->save();

                if (! kart()->cart()->canCheckout()) {
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

                /** @var ProductPage|null $page */
                $page = page('page://'.Router::get('product'));
                $variant = $page?->variantFromRequestData(kirby()->request()->data());
                kart()->cart()->remove(
                    $page,
                    max(1, intval(Router::get('amount', 1))),
                    variant: $variant
                );
                kart()->cart()->save();

                return Router::go(Router::idWithParams(Router::CART_REMOVE));
            },
        ],
        [
            'pattern' => Router::CART_CHECKOUT,
            'method' => 'POST',
            'action' => function () {
                if ($r = Router::denied([
                    Router::class.'::hasCaptcha',
                    Router::class.'::hasTurnstile',
                ])) {
                    return $r;
                }

                if (! kart()->cart()->canCheckout()) {
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

                /** @var ProductPage|null $page */
                $page = page('page://'.Router::get('product'));
                $variant = $page?->variantFromRequestData(kirby()->request()->data());
                kart()->cart()->remove(
                    $page,
                    999, // aka all,
                    variant: $variant
                );
                kart()->cart()->save();
                kart()->wishlist()->add(
                    $page,
                    variant: $variant
                );
                kart()->wishlist()->save();

                return Router::go(Router::idWithParams(Router::CART_LATER));
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
                        'title' => t('bnomei.kart.payment'),
                    ],
                ]);

                return site()->visit($payment);
            },
        ],
        [
            'pattern' => Router::PROVIDER_SYNC,
            'method' => 'GET',
            'action' => function () {
                if ($r = Router::denied([
                    Router::class.'::hasAdmin',
                ])) {
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
        [
            'pattern' => '(:all)',
            'action' => function ($path) {
                if ($r = Router::denied([
                    Router::class.'::hasRatelimit',
                ], exclusive: true)) {
                    return $r;
                }

                if (kirby()->request()->header('Accept') === 'application/json' &&
                    in_array($path, kart()->option('router.snippets'))) {
                    return Router::go();
                }

                $this->next();
            },
        ],
    ];
};
