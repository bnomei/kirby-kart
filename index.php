<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\DotEnv;
use Bnomei\Kart\Cart;
use Bnomei\Kart\Kart;
use Bnomei\Kart\License;
use Bnomei\Kart\MagicLinkChallenge;
use Bnomei\Kart\Router;
use Bnomei\Kart\Wishlist;
use Kirby\Cms\App;
use Kirby\Cms\Collection;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\User;
use Kirby\Cms\Users;
use Kirby\Content\Field;
use Kirby\Data\Yaml;
use Kirby\Http\Remote;
use Kirby\Session\Session;
use Kirby\Toolkit\A;
use SimpleCaptcha\Builder;

@include_once __DIR__.'/vendor/autoload.php';

if (! function_exists('kart')) {
    function kart(): Kart
    {
        return Kart::singleton();
    }
}

App::plugin(
    name: 'bnomei/kart',
    extends: [
        'options' => [
            'license' => fn () => class_exists('\Bnomei\DotEnv') ? DotEnv::getenv('KART_LICENSE_KEY') : '', // set your license from https://buy-kart.bnomei.com code in the config `bnomei.kart.license`
            'cache' => [
                'categories' => true,
                'crypto' => true,
                'gravatar' => true,
                'orders' => true,
                'products' => true,
                'queue' => true,
                'ratelimit' => true,
                'stats' => true,
                'stocks' => true,
                'stocks-holds' => true,
                'tags' => true,

                // providers
                'fastspring' => true,
                'gumroad' => true,
                'invoice_ninja' => true,
                'kirby_cms' => true,
                'lemonsqueeze' => true,
                'mollie' => true,
                'paddle' => true,
                'paypal' => true,
                'payone' => true,
                'snipcart' => true,
                'stripe' => true,
            ],
            'expire' => 0, // 0 = forever, null to disable caching
            'customers' => [
                'enabled' => true,
                'roles' => ['customer', 'member', 'admin'], // does NOT include `deleted`
            ],
            'crypto' => [
                'password' => fn () => class_exists('\Bnomei\DotEnv') ? DotEnv::getenv('CRYPTO_PASSWORD') : null,
            ],
            'locale' => 'en_EN', // or current locale on multilanguage setups
            'currency' => 'EUR', // uppercase 3-letter code
            'successPage' => null, // id of the page to redirect to after checkout flow, defaults to page of order
            'orders' => [
                'enabled' => true,
                'page' => 'orders',
                'order' => [
                    'uuid' => fn (?OrdersPage $orders, array $props) => 'or-'.Kart::nonAmbiguousUuid(7), // aka order id
                    'create-missing-zips' => true,
                    'maxapo' => 10, // max amount of a single product per order, keep this low to prevent stock hostages, set per product instead
                    'maxlpo' => 10, // max different products per order aka lines in cart, check your providers API docs before increasing this
                ],
            ],
            'products' => [
                'enabled' => true,
                'page' => 'products',
                'product' => [
                    'uuid' => fn (?ProductsPage $products, array $props) => 'pr-'.Kart::hash(A::get($props, 'id')),
                ],
            ],
            'queues' => [
                'locking' => true, // with flock while reading
            ],
            'stocks' => [
                'enabled' => true,
                'queue' => true, // instead of calling $page->increment() it will queue them which is better when dealing with concurrent requests
                'hold' => false, // null/false or time in minutes as integer (only for logged-in users!)
                'page' => 'stocks',
                'stock' => [
                    'uuid' => fn (?StocksPage $stocks, array $props) => 'st-'.Kart::nonAmbiguousUuid(13),
                ],
            ],
            'router' => [
                'mode' => 'go', // go/json/html
                'encryption' => fn () => sha1(__DIR__), // or false
                'csrf' => 'token', // null|false or name of form data
                'header' => [
                    'csrf' => 'X-CSRF-TOKEN',
                    'htmx' => 'HX-Request',
                ],
                'snippets' => [
                    // define the snippets that are allowed to be called
                    'kart/cart-add',
                    'kart/cart-buy',
                    'kart/cart-later', // dummy
                    'kart/cart-remove', // dummy
                    'kart/captcha',
                    'kart/login',
                    'kart/login-magic',
                    'kart/signup-magic',
                    'kart/wish-or-forget',
                    'kart/wishlist-add' => 'kart/wish-or-forget.htmx',  // htmx
                    'kart/wishlist-now',  // dummy
                    'kart/wishlist-remove' => 'kart/wish-or-forget.htmx',  // htmx
                    // overwrite to change or set your own
                ],
            ],
            'middlewares' => [
                'csrf' => 'token', // null|false or name of form data
                'ratelimit' => [
                    'enabled' => true,
                    'limit' => 30 * 60, // N requests in 60 seconds
                ],
                'enabled' => function (): array {
                    // could do different stuff based on kirby()->request()
                    return [
                        Router::class.'::hasCsrf',
                        Router::class.'::hasRatelimit',
                        // ... static class::method or closures
                    ];
                },
            ],
            'provider' => 'kirby_cms', // see ProviderEnum (kirby_cms, stripe, mollie, paddle, ...) or use \Kart\Provider\Kirby::class etc.
            'providers' => [
                'fastspring' => [],
                'gumroad' => [
                    'access_token' => fn () => class_exists('\Bnomei\DotEnv') ? DotEnv::getenv('GUMROAD_ACCESS_TOKEN') : null,
                    'virtual' => true,
                ],
                'invoice_ninja' => [],
                'kirby_cms' => [
                    'virtual' => false,
                ],
                'lemonsqueeze' => [],
                'mollie' => [],
                'paddle' => [
                    // https://developer.paddle.com/api-reference/overview
                    'endpoint' => fn () => class_exists('\Bnomei\DotEnv') ? DotEnv::getenv('PADDLE_ENDPOINT', 'https://sandbox-api.paddle.com') : 'https://sandbox-api.paddle.com',
                    'public_token' => fn () => class_exists('\Bnomei\DotEnv') ? DotEnv::getenv('PADDLE_PUBLIC_TOKEN') : null,
                    'secret_key' => fn () => class_exists('\Bnomei\DotEnv') ? DotEnv::getenv('PADDLE_SECRET_KEY') : null,
                    'checkout_options' => function (Kart $kart) {
                        // configure the checkout based on current kart instance
                        // https://developer.paddle.com/api-reference/transactions/create-transaction
                        return [];
                    },
                    'virtual' => true,
                ],
                'payone' => [],
                'paypal' => [
                    'endpoint' => fn () => class_exists('\Bnomei\DotEnv') ? DotEnv::getenv('PAYPAL_ENDPOINT', 'https://api-m.sandbox.paypal.com') : 'https://api-m.sandbox.paypal.com',
                    'client_id' => fn () => class_exists('\Bnomei\DotEnv') ? DotEnv::getenv('PAYPAL_CLIENT_ID') : null,
                    'client_secret' => fn () => class_exists('\Bnomei\DotEnv') ? DotEnv::getenv('PAYPAL_CLIENT_SECRET') : null,
                    'checkout_options' => function (Kart $kart) {
                        // configure the checkout based on current kart instance
                        // https://developer.paypal.com/docs/api/orders/v2/#orders_create
                        return [];
                    },
                    'virtual' => ['title', 'description', 'gallery'],
                ],
                'snipcart' => [

                ],
                'stripe' => [
                    'secret_key' => fn () => class_exists('\Bnomei\DotEnv') ? DotEnv::getenv('STRIPE_SECRET_KEY') : null,
                    'checkout_options' => function (Kart $kart) {
                        // configure the checkout based on current kart instance
                        // https://docs.stripe.com/api/checkout/sessions/create
                        return [];
                    },
                    'virtual' => true,
                ],
            ],
            'turnstile' => [
                'endpoint' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                'sitekey' => fn () => class_exists('\Bnomei\DotEnv') ? DotEnv::getenv('TURNSTILE_SITE_KEY') : null,
                'secretkey' => fn () => class_exists('\Bnomei\DotEnv') ? DotEnv::getenv('TURNSTILE_SECRET_KEY') : null,
            ],
            'captcha' => [
                'current' => function () {
                    return get('captcha'); // in current request
                },
                'set' => function (bool $inline = true) {
                    // https://github.com/S1SYPHOS/php-simple-captcha
                    $builder = new Builder;
                    $builder->bgColor = '#FFFFFF';
                    $builder->lineColor = '#FFFFFF';
                    $builder->textColor = '#000000';
                    $builder->applyEffects = false;
                    $builder->build();
                    kirby()->session()->set('captcha', $builder->phrase);

                    // the image
                    if (! $inline) {
                        $builder->output();
                        exit();
                    }

                    // json
                    return [
                        'captcha' => $builder->inline(),
                    ];
                },
                'get' => function () {
                    // stored in session from captcha route
                    return kirby()->session()->get('captcha');
                },
            ],
        ],
        'routes' => require_once __DIR__.'/routes.php',
        'snippets' => [
            'kart/account-delete' => __DIR__.'/snippets/kart/account-delete.php',
            'kart/captcha' => __DIR__.'/snippets/kart/captcha.php',
            'kart/cart' => __DIR__.'/snippets/kart/cart.php',
            'kart/cart-add' => __DIR__.'/snippets/kart/cart-add.php',
            'kart/cart-buy' => __DIR__.'/snippets/kart/cart-buy.php',
            'kart/checkout-json-ld' => __DIR__.'/snippets/kart/checkout-json-ld.php',
            'kart/email-login-magic' => __DIR__.'/snippets/kart/email-login-magic.php',
            'kart/email-login-magic.html' => __DIR__.'/snippets/kart/email-login-magic.html.php',
            'kart/input-csrf' => __DIR__.'/snippets/kart/input-csrf.php',
            'kart/input-csrf-defer' => __DIR__.'/snippets/kart/input-csrf-defer.php',
            'kart/kart' => __DIR__.'/snippets/kart/kart.php',
            'kart/login' => __DIR__.'/snippets/kart/login.php',
            'kart/login-magic' => __DIR__.'/snippets/kart/login-magic.php',
            'kart/logout' => __DIR__.'/snippets/kart/logout.php',
            'kart/order.pdf' => __DIR__.'/snippets/kart/order.pdf.php',
            'kart/paddle-checkout' => __DIR__.'/snippets/kart/paddle-checkout.php',
            'kart/product-card' => __DIR__.'/snippets/kart/product-card.php',
            'kart/product-json-ld' => __DIR__.'/snippets/kart/product-json-ld.php',
            'kart/profile' => __DIR__.'/snippets/kart/profile.php',
            'kart/signup-magic' => __DIR__.'/snippets/kart/signup-magic.php',
            'kart/turnstile-form' => __DIR__.'/snippets/kart/turnstile-form.php',
            'kart/turnstile-widget' => __DIR__.'/snippets/kart/turnstile-widget.php',
            'kart/wish-or-forget' => __DIR__.'/snippets/kart/wish-or-forget.php',
            'kart/wish-or-forget.htmx' => __DIR__.'/snippets/kart/wish-or-forget.htmx.php',
            'kart/wishlist' => __DIR__.'/snippets/kart/wishlist.php',
        ],
        'authChallenges' => [
            'kart-magic-link' => MagicLinkChallenge::class,
        ],
        'translations' => [
            'da' => require_once __DIR__.'/translations/da.php',
            'de' => require_once __DIR__.'/translations/de.php',
            'en' => require_once __DIR__.'/translations/en.php',
            'es_ES' => require_once __DIR__.'/translations/es_ES.php',
            'fr' => require_once __DIR__.'/translations/fr.php',
            'it' => require_once __DIR__.'/translations/it.php',
            'tr' => require_once __DIR__.'/translations/tr.php',
        ],
        'blueprints' => [
            'users/customer' => CustomerUser::phpBlueprint(),
            'users/deleted' => DeletedUser::phpBlueprint(),
            'pages/order' => OrderPage::phpBlueprint(),
            'pages/orders' => OrdersPage::phpBlueprint(),
            'pages/product' => ProductPage::phpBlueprint(),
            'pages/products' => ProductsPage::phpBlueprint(),
            'pages/stock' => StockPage::phpBlueprint(),
            'pages/stocks' => StocksPage::phpBlueprint(),
        ],
        'templates' => [
            'cart' => __DIR__.'/templates/cart.php',
            'kart' => __DIR__.'/templates/kart.php',
            'login' => __DIR__.'/templates/login.php',
            'order' => __DIR__.'/templates/order.php',
            'order.pdf' => __DIR__.'/templates/order.pdf.php',
            'order.zip' => __DIR__.'/templates/order.zip.php',
            'orders' => __DIR__.'/templates/orders.php',
            'payment' => __DIR__.'/templates/payment.php',
            'product' => __DIR__.'/templates/product.php',
            'products' => __DIR__.'/templates/products.php',
            'signup' => __DIR__.'/templates/signup.php',
            'stock' => __DIR__.'/templates/stock.php',
            'stocks' => __DIR__.'/templates/stocks.php',
        ],
        'pageModels' => [
            'order' => OrderPage::class,
            'orders' => OrdersPage::class,
            'product' => ProductPage::class,
            'products' => ProductsPage::class,
            'stock' => StockPage::class,
            'stocks' => StocksPage::class,
        ],
        'hooks' => [
            'system.loadPlugins:after' => function (): void {
                // make sure the kart singleton is ready in calling it once
                kart();
            },
            'user.login:after' => function (User $user, Session $session) {
                kart()->cart()->merge($user);
                kart()->cart()->save();
                kart()->wishlist()->merge($user);
                kart()->wishlist()->save();
            },
            'user.logout:after' => function (User $user, Session $session) {
                kart()->cart()->releaseStock();
                kart()->cart()->clear();
                kart()->cart()->save(false);
                kart()->wishlist()->clear();
                kart()->wishlist()->save(false);
            },
            'user.create:after' => function (User $user) {
                kirby()->cache('bnomei.kart.stats')->remove('customers');
            },
            'user.changeRole:after' => function (User $newUser, User $oldUser) {
                kirby()->cache('bnomei.kart.stats')->remove('customers');
            },
            'user.delete:after' => function (bool $status, User $user) {
                kirby()->cache('bnomei.kart.stats')->remove('customers');
            },
            'page.created:after' => function (Page $page) {
                if ($page instanceof StocksPage) {
                    kirby()->cache('bnomei.kart.stocks')->flush();
                    kirby()->cache('bnomei.kart.stocks-holds')->flush();
                } elseif ($page instanceof OrdersPage) {
                    kirby()->cache('bnomei.kart.orders')->flush();
                } elseif ($page instanceof ProductsPage) {
                    kirby()->cache('bnomei.kart.categories')->flush();
                    kirby()->cache('bnomei.kart.products')->flush();
                    kirby()->cache('bnomei.kart.tags')->flush();
                }
            },
            'page.update:before' => function (Page $page, array $values, array $strings): void {
                if ($page instanceof StockPage) {
                    kirby()->cache('bnomei.kart.stocks')->flush();
                    if (! $page->onlyOneStockPagePerProduct($values)) {
                        throw new Exception(strval(t('bnomei.kart.stocks.exception-uniqueness')));
                    }
                } elseif ($page instanceof StocksPage) {
                    kirby()->cache('bnomei.kart.stocks')->flush();
                } elseif ($page instanceof OrderPage || $page instanceof OrdersPage) {
                    kirby()->cache('bnomei.kart.orders')->flush();
                } elseif ($page instanceof ProductPage || $page instanceof ProductsPage) {
                    kirby()->cache('bnomei.kart.categories')->flush();
                    kirby()->cache('bnomei.kart.products')->flush();
                    kirby()->cache('bnomei.kart.tags')->flush();
                }
            },
            'page.update:after' => function (Page $newPage, Page $oldPage): void {
                if ($newPage instanceof OrderPage) {
                    // update the max orders invnumber with current
                    // to allow for manual pushing of the number to
                    // higher values. like start with #12345
                    $newPage->updateInvoiceNumber();
                }
            },
            'page.delete:after' => function (bool $status, Page $page) {
                if ($page instanceof StockPage) {
                    kirby()->cache('bnomei.kart.stocks')->flush();
                }
            },
            /*
            // KART
            'kart.log' => function ($ex): void {
                //
            },
            'kart.cart.completed' => function (?User $user = null, ?OrderPage $order = null): void {
                // fulfillment hook of Cart::complete()
            },
            'kart.provider.*.checkout' => function (): void {
                // kart()->provider()
            },
            'kart.provider.*.cancelled' => function (): void {
                // kart()->provider()
            },
            'kart.provider.*.completed' => function (array $data = []): void {
                // kart()->provider()
            },
            'kart.stock.updated' => function (StockPage $stock, int $amount): void {
                // StockPage::updateStock()
            },
            'kart.user.created' => function (?User $user = null): void {
                // TIP: use default kirby hook to track delete, or kart.user.softDeleted
                // TIP: send a magic login email
                // $user?->sendMagicLink();
                // TIP: or a discord notification to yourself
            },
            'kart.user.softDeleted' => function (?User $user = null): void {
                // TIP: or a discord notification to yourself
            },
            'kart.user.signup' => function (?User $user = null): void {
                // NOTE: this will happen in ADDITION to kart.user.created when the signup form is used
            },
            'kart.cart.add' => function (ProductPage $product, int $count, ?CartLine $item = null, ?User $user = null): void {
                // kart()->cart()
            },
            'kart.cart.remove' => function (ProductPage $product, int $count, ?CartLine $item = null, ?User $user = null): void {
                // kart()->cart()
            },
            'kart.cart.clear' => function (?User $user = null): void {
                // kart()->cart()
            },
            'kart.wishlist.add' => function (ProductPage $product, int $count, ?CartLine $item = null, ?User $user = null): void {
                // kart()->wishlist()
            },
            'kart.wishlist.remove' => function (ProductPage $product, int $count, ?CartLine $item = null, ?User $user = null): void {
                // kart()->wishlist()
            },
            'kart.wishlist.clear' => function (?User $user = null): void {
                // kart()->wishlist()
            },
            'kart.ratelimit.hit' => function (string $ip, string $key, int $count, int $limit): void {
                // Ratelimit::check()
            },
            */
        ],
        'fieldMethods' => [
            /**
             * @kql-allowed
             */
            'toFormattedNumber' => function ($field, bool $prefix = false): string {
                $field->value = Kart::formatNumber(floatval($field->value), $prefix);

                return $field;
            },
            /**
             * @kql-allowed
             */
            'toFormattedCurrency' => function (Field $field): string {
                $field->value = Kart::formatCurrency(floatval($field->value));

                return $field;
            },
            /**
             * @kql-allowed
             */
            'toCategories' => function (Field $field): Collection {
                return kart()->categories()->filterBy('value', 'in', explode(',', strval($field->value)));
            },
            /**
             * @kql-allowed
             */
            'toTags' => function (Field $field): Collection {
                return kart()->tags()->filterBy('value', 'in', explode(',', strval($field->value)));
            },
        ],
        'pagesMethods' => [
            /**
             * @kql-allowed
             */
            'sum' => function (string $field): float|int {
                /** @var Pages $pages */
                $pages = $this;

                return array_sum($pages->values(function (Page $page) use ($field) {
                    if (property_exists($page, $field)) {
                        return $page->$field;
                    }

                    if (method_exists($page, $field)) {
                        return $page->$field();
                    }

                    if ($page->$field() instanceof Field) {
                        return $page->$field()->toFloat();
                    }

                    return 0;
                }));
            },
            /**
             * @kql-allowed
             */
            'sumField' => function (string $field): Field {
                return new Field(null, $field, $this->sum($field));
            },
            /**
             * @kql-allowed
             */
            'interval' => function (string $field, string $from, ?string $until = null): Pages {
                $from = strtotime($from);
                $until = $until ? strtotime($until) : null;

                return $this->filterBy(function ($page) use ($field, $from, $until) {
                    $ts = intval($page->$field()->toDate('U'));

                    return $ts >= $from && (! $until || $ts <= $until);
                });
            },
            /**
             * @kql-allowed
             */
            'trend' => function (string $field, string $compare): Field {
                return $this->interval($field, '-30 days', 'now')->sumField($compare);
            },
            /**
             * @kql-allowed
             */
            'trendPercent' => function (string $field, string $compare): Field {
                $current = $this->interval($field, '-30 days', 'now')->sum($compare);
                $last = $this->interval($field, '-60 days', '-31 days')->sum($compare);

                if ($last == 0) {
                    $diff = ($current > 0) ? 100.0 : 0.0; // If last month was 0 and this month is positive, assume 100% increase
                } else {
                    $diff = (($current - $last) / $last) * 100.0;
                }

                return new Field(null, $field, $diff);
            },
            'trendTheme' => function (string $field, string $compare): string {
                $current = $this->interval($field, '-30 days', 'now')->sum($compare);
                $last = $this->interval($field, '-60 days', '-31 days')->sum($compare);

                return $current >= $last ? 'positive' : 'negative';
            },
        ],
        'fileMethods' => [
            'modifiedAt' => function (?string $format = null): string {
                $format = $format ?: 'Y-m-d H:i'; // without seconds like kirbys time field
                $modified = $this->modified();

                return is_int($modified) ? date($format, $modified) : '?';
            },
        ],
        'pageMethods' => [
            /**
             * @kql-allowed
             */
            'kart' => function (): Kart {
                return kart();
            },
            'dump' => function (?string $field = null, int $maxWidth = 140): string {
                /** @var Page $page */
                $page = $this;
                $content = $page->content()->toArray();
                if ($field) {
                    $content = A::get($content, $field, []);
                    try { // if the field is a yaml/json content
                        if (is_string($content) && json_decode($content)) {
                            $content = json_decode($content);
                        }
                        if (is_string($content)) {
                            $content = Yaml::decode($content);
                        }
                    } catch (Throwable $th) {
                        // ignore
                    }
                }

                // format a json for the dump
                $json = json_encode($content, JSON_PRETTY_PRINT) ?: '';
                $lines = explode("\n", $json);
                $wrappedLines = [];

                foreach ($lines as $line) {
                    $indentation = strspn($line, ' ');
                    $content = trim($line);
                    $wrapped = wordwrap($content, $maxWidth - $indentation, "\n", true);
                    $wrapped = preg_replace('/^/m', str_repeat(' ', $indentation), $wrapped);
                    $wrappedLines[] = $wrapped;
                }

                $json = implode("\n", $wrappedLines);
                $json = str_replace([' ', '&nbsp;&nbsp;'], ['&nbsp;', '&nbsp;'], $json);

                return '<code>'.$json.'</code>';
            },
        ],
        'siteMethods' => [
            /**
             * @kql-allowed
             */
            'kart' => function (): Kart {
                return kart();
            },
        ],
        'usersMethods' => [
            /**
             * @kql-allowed
             */
            'customers' => function (): ?Users {
                return $this->filterBy('role', 'in', kart()->option('customers.roles'));
            },
        ],
        'userMethods' => [
            /**
             * @kql-allowed
             */
            'kart' => function (): Kart {
                return kart();
            },
            /**
             * @kql-allowed
             */
            'cart' => function (): Cart {
                return kart()->cart();
            },
            /**
             * @kql-allowed
             */
            'wishlist' => function (): Wishlist {
                return kart()->wishlist();
            },
            /**
             * @kql-allowed
             */
            'isCustomer' => function (): bool {
                return in_array($this->role()->name(), (array) kart()->option('customers.roles'));
            },
            /**
             * @kql-allowed
             */
            'orders' => function (): Pages {
                $expire = kart()->option('expire');
                if (is_int($expire)) {
                    return new Pages(array_filter(kirby()->cache('bnomei.kart.orders')->getOrSet($this->id(), function () {
                        return array_values(kart()->orders()
                            ->filterBy(fn (OrderPage $order) => $order->customer()->toUser()?->id() === $this->id())
                            ->sortBy('paidDate', 'desc')
                            ->toArray(fn (OrderPage $order) => $order->uuid()->toString()));
                    }, $expire), fn ($id) => $this->kirby()->page($id)));
                }

                return kart()->orders()
                    ->filterBy(fn (OrderPage $order) => $order->customer()->toUser()?->id() === $this->id())
                    ->sortBy('paidDate', 'desc');
            },
            /**
             * @kql-allowed
             */
            'completedOrders' => function (): Pages {
                /** @var CustomerUser $user */
                $user = $this;

                return $this->orders()
                    ->filterBy(fn (OrderPage $order) => $order->paymentComplete()->toBool());
            },
            /**
             * @kql-allowed
             */
            'hasPurchased' => function (ProductPage|string $product): bool {
                /** @var OrderPage $order */
                foreach ($this->orders() as $order) {
                    if ($order->hasProduct($product)) {
                        return true;
                    }
                }

                return false;
            },
            /**
             * @todo
             *
             * @kql-allowed
             */
            /*
            'hasMadePaymentFor' => function (string $provider, ProductPage $productPage): bool {
                /** @var CustomerUser $user * /
                $user = $this;
                if ($this->$provider()->isEmpty()) {
                    return false;
                }
                // try finding a payment like KLUB would store it on fulfillment of one_time purchases
                // which is stripe.payments[<array of price_ids>]
                $data = $this->$provider()->yaml();

                return count(array_intersect(
                    $productPage->priceIds(),
                    A::get($data, 'payments', [])
                )) > 0;
            },
            */
            /**
             * @kql-allowed
             */
            'owns' => function (ProductPage|string $product): bool {
                return $this->hasPurchased($product) ||
                    $this->hasMadePaymentFor(kart()->provider()->name(), $product);
            },
            /**
             * @kql-allowed
             */
            'gravatar' => function (int $size = 200): string {
                $hash = md5(strtolower(trim($this->email())));
                $url = "https://www.gravatar.com/avatar/{$hash}?s={$size}";

                if ($cache = kirby()->cache('bnomei.kart.gravatar')->get(md5($url))) {
                    return $cache;
                }

                $image = Remote::get($url);
                if ($image->code() === 200) {
                    $image = $image->content();
                    if (! is_string($image)) {
                        return $url;
                    }

                    $imageInfo = getimagesizefromstring($image) ?: [];
                    $mimeType = A::get($imageInfo, 'mime', 'image/png');
                    $base64 = base64_encode($image);
                    $dataUrl = "data:{$mimeType};base64,{$base64}";

                    // using the bnomei.kart.expire does not make sense for the long-lived url
                    kirby()->cache('bnomei.kart.gravatar')->set(md5($url), $dataUrl, 60 * 24);

                    return $dataUrl;
                }

                return $url;
            },
            'softDelete' => function (): void {
                $user = $this;
                kirby()->impersonate('kirby', function () use ($user) {
                    $user = $user->changeRole('deleted');
                    $user = $user->update(['deletedAt' => time()]);
                    kirby()->trigger('kart.user.softDeleted', [
                        'user' => $user,
                    ]);
                });
            },
            'sendMagicLink' => function (?string $success_url = null): void {
                /** @var User $user */
                $user = $this;
                $code = MagicLinkChallenge::create($this, [
                    'mode' => 'login',
                    'timeout' => 10 * 60,
                    'email' => $this->email(),
                    'name' => $this->nameOrEmail(),
                    'success_url' => $success_url ?? url('/'),
                ]);
                if ($code) {
                    kirby()->session()->set('kirby.challenge.type', 'login');
                    kirby()->session()->set('kirby.challenge.code', password_hash($code, PASSWORD_DEFAULT));
                }
            },
        ],
        'commands' => [
            'kart:blueprints-publish' => [
                'description' => 'Publish Kart Blueprints',
                'command' => static function ($cli): void {
                    foreach ([
                        'users/customer' => CustomerUser::class,
                        'pages/order' => OrderPage::class,
                        'pages/orders' => OrdersPage::class,
                        'pages/product' => ProductPage::class,
                        'pages/products' => ProductsPage::class,
                        'pages/stock' => StockPage::class,
                    ] as $name => $class) {
                        Yaml::write(__DIR__."/blueprints/{$name}.yml", $class::phpBlueprint());
                    }
                },
            ],
            'kart:flush' => [
                'description' => 'Flush Kart Cache(s)',
                'args' => [
                    'name' => [
                        'prefix' => 'n',
                        'longPrefix' => 'name',
                        'description' => 'Name of the cache to flush [*/all/ratelimit/stripe/...].',
                        'defaultValue' => 'all', // flush all
                        'castTo' => 'string',
                    ],
                ],
                'command' => static function ($cli): void {
                    $name = $cli->arg('name');
                    $cli->out("🚽 Flushing Kart Cache [$name]...");
                    Kart::flush($name);
                    $cli->success('✅ Done.');

                    if (function_exists('janitor')) {
                        janitor()->data($cli->arg('command'), [
                            'status' => 200,
                            'message' => "Kart Cache [$name] flushed.",
                        ]);
                    }
                },
            ],
        ],
    ],
    license: fn ($plugin) => new License($plugin, License::NAME));
