<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

// use Bnomei\DotEnv; // NOTE: would break if not installed
use Bnomei\Kart\CaptchaBuilder;
use Bnomei\Kart\Cart;
use Bnomei\Kart\CartLine;
use Bnomei\Kart\Kart;
use Bnomei\Kart\License;
use Bnomei\Kart\MagicLinkChallenge;
use Bnomei\Kart\Router;
use Bnomei\Kart\UuidCache;
use Bnomei\Kart\Wishlist;
use Kirby\Cms\App;
use Kirby\Cms\Block;
use Kirby\Cms\Collection;
use Kirby\Cms\Layout;
use Kirby\Cms\LayoutColumn;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\Site;
use Kirby\Cms\User;
use Kirby\Cms\Users;
use Kirby\Content\Field;
use Kirby\Data\Yaml;
use Kirby\Http\Remote;
use Kirby\Session\Session;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

@include_once __DIR__.'/vendor/autoload.php';

if (! function_exists('kart')) {
    function kart(): Kart
    {
        return Kart::singleton();
    }
}

if (! function_exists('kerbs')) {
    function kerbs(?array $props = null, ?string $template = null): void
    {
        snippet('kerbs/inertia', array_filter(['props' => $props, 'template' => $template]));
    }
}

if (! function_exists('kart_env')) {
    function kart_env(string $key, mixed $default = null): mixed
    {
        // NOTE: using a string prevents the IDE to put it in a use statement which would break if the class is not loaded
        $dotenv = '\Bnomei\DotEnv';

        return class_exists($dotenv) ? $dotenv::getenv($key, $default) : $default;
    }
}

App::plugin(
    name: 'bnomei/kart',
    extends: [
        'options' => [
            'license' => fn () => kart_env('KART_LICENSE_KEY', ''), // set your license from https://buy-kart.bnomei.com code in the config `bnomei.kart.license`
            'cache' => [
                'categories' => true,
                'crypto' => true, // used to store a SALT
                'gravatar' => true,
                'licenses' => true,
                'orders' => true,
                'products' => true,
                'router' => true, // used to store a SALT
                'queue' => true,
                'ratelimit' => true, // GC in kart->ready
                'stats' => true,
                'stocks' => true,
                'stocks-holds' => true,
                'tags' => true,

                // providers
                'checkout' => true,
                'fastspring' => true,
                'gumroad' => true,
                'invoice_ninja' => true,
                'kirby_cms' => true,
                'lemonsqueezy' => true,
                'mollie' => true,
                'paddle' => true,
                'paypal' => true,
                'payone' => true,
                'shopify' => true,
                'square' => true,
                'snipcart' => true,
                'stripe' => true,
                'sumup' => true,
            ],
            'expire' => 0, // 0 = forever, null to disable caching
            'customers' => [
                'enabled' => true,
                'roles' => ['customer', 'member', 'admin'], // does NOT include `deleted`
            ],
            'crypto' => [
                'password' => fn () => kart_env('CRYPTO_PASSWORD'),
                'salt' => fn () => kart_env(
                    'CRYPTO_SALT',
                    kirby()->cache('bnomei.kart.crypto')->getOrSet('salt', fn () => Str::random(64))
                ),
            ],
            'locale' => 'en_EN', // or current locale on multilanguage setups
            'currency' => 'EUR', // uppercase 3-letter code
            'successPage' => null, // id of the page to redirect to after checkout flow, defaults to page of order
            'dateformat' => 'Y-m-d H:i',
            'orders' => [
                'enabled' => true,
                'page' => 'orders',
                'order' => [
                    'uuid' => fn (?OrdersPage $orders = null, array $props = []) => 'or-'.Kart::nonAmbiguousUuid(7), // aka order id
                    'create-missing-zips' => true,
                    'maxapo' => 10, // max amount of a single product per order, keep this low to prevent stock hostages, set per product instead
                    'maxlpo' => 10, // max different products per order aka lines in cart, check your providers API docs before increasing this
                ],
            ],
            'products' => [
                'enabled' => true,
                'page' => 'products',
                'product' => [
                    'uuid' => fn (?ProductsPage $products = null, array $props = []) => 'pr-'.Kart::hash(A::get($props, 'id', Kart::nonAmbiguousUuid(7))),
                ],
                'variants' => [ // overwrite and define your own sorting orders
                    'size' => ['XS', 'xs', 'S', 's', 'M', 'm', 'L', 'l', 'XL', 'xl', 'XXL', 'xxl'],
                ],
            ],
            'licenses' => [
                'api' => false, // API endpoints are disabled by default
                'license' => [
                    'uuid' => fn (array $data = []) => Str::uuid(),
                ],
                'activate' => function (string $check, ?string $found = null, ?OrderPage $order = null, ?User $user = null) {
                    return [];
                },
                'deactivate' => function (string $check, ?string $found = null, ?OrderPage $order = null, ?User $user = null) {
                    return [];
                },
                'validate' => function (string $check, ?string $found = null, ?OrderPage $order = null, ?User $user = null) {
                    return [];
                },
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
                    'uuid' => fn (?StocksPage $stocks = null, array $props = []) => 'st-'.Kart::nonAmbiguousUuid(13),
                ],
            ],
            'router' => [
                'mode' => 'go', // go/json/html
                'salt' => fn () => kart_env(
                    'ROUTER_SALT',
                    kirby()->cache('bnomei.kart.router')->getOrSet('salt', fn () => Str::random(64))
                ), // or false
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
                    'limit' => 60, // N requests within one minute
                ],
                'enabled' => function (): array {
                    // could do different stuff based on kirby()->request()
                    return [
                        Router::class.'::hasBlacklist',
                        Router::class.'::hasCsrf',
                        Router::class.'::hasRatelimit',
                        // ... static class::method or closures
                    ];
                },
                'blacklist' => [
                    // add any path from kart you want to block like...
                    // Router::LOGIN,
                    // Router::LOGOUT,
                    // Router::SIGNUP_MAGIC,
                ],
            ],
            'provider' => 'kirby_cms', // see ProviderEnum (kirby_cms, stripe, mollie, paddle, ...) or use \Kart\Provider\Kirby::class etc.
            'providers' => [
                'checkout' => [],
                'fastspring' => [
                    'store_url' => fn () => kart_env('FASTSPRING_STORE_URL', 'https://acme.onfastspring.com'),
                    'username' => fn () => kart_env('FASTSPRING_USERNAME'),
                    'password' => fn () => kart_env('FASTSPRING_PASSWORD'),
                    'checkout_options' => function (Kart $kart) {
                        // configure the checkout based on current kart instance
                        // https://developer.fastspring.com/docs/storefront-urls#link-to-your-checkouts-with-the-api
                        return [];
                    },
                    'checkout_line' => function (Kart $kart, CartLine $line) {
                        // add custom data to the current checkout line
                        return [];
                    },
                    'virtual' => ['raw', 'title', 'description', 'gallery'],
                ],
                'gumroad' => [
                    'access_token' => fn () => kart_env('GUMROAD_ACCESS_TOKEN'),
                    'virtual' => ['raw', 'description', 'gallery', 'price', 'tags', 'title'],
                ],
                'invoice_ninja' => [],
                'kirby_cms' => [
                    'virtual' => false,
                ],
                'lemonsqueezy' => [
                    'store_id' => fn () => kart_env('LEMONSQUEEZY_STORE_ID'),
                    'secret_key' => fn () => kart_env('LEMONSQUEEZY_SECRET_KEY'),
                    'checkout_options' => function (Kart $kart) {
                        // configure the checkout based on current kart instance
                        // https://docs.lemonsqueezy.com/api/checkouts/create-checkout
                        return [];
                    },
                    'virtual' => ['raw', 'description', 'gallery', 'price', 'variants', 'title'],
                ],
                'mollie' => [
                    'secret_key' => fn () => kart_env('MOLLIE_SECRET_KEY'),
                    'checkout_options' => function (Kart $kart) {
                        // configure the checkout based on current kart instance
                        // https://docs.mollie.com/reference/create-payment
                        return [];
                    },
                    'checkout_line' => function (Kart $kart, CartLine $line) {
                        // add custom data to the current checkout line
                        return [];
                    },
                    'virtual' => false,
                ],
                'paddle' => [
                    // https://developer.paddle.com/api-reference/overview
                    'endpoint' => fn () => kart_env('PADDLE_ENDPOINT', 'https://sandbox-api.paddle.com'),
                    'public_token' => fn () => kart_env('PADDLE_PUBLIC_TOKEN'),
                    'secret_key' => fn () => kart_env('PADDLE_SECRET_KEY'),
                    'checkout_options' => function (Kart $kart) {
                        // configure the checkout based on current kart instance
                        // https://developer.paddle.com/api-reference/transactions/create-transaction
                        return [];
                    },
                    'checkout_line' => function (Kart $kart, CartLine $line) {
                        // add custom data to the current checkout line
                        return [];
                    },
                    'virtual' => ['raw', 'description', 'gallery', 'downloads', 'price', 'tags', 'categories', 'variants', 'title', 'featured'],
                ],
                'payone' => [],
                'paypal' => [
                    'endpoint' => fn () => kart_env('PAYPAL_ENDPOINT', 'https://api-m.sandbox.paypal.com'),
                    'client_id' => fn () => kart_env('PAYPAL_CLIENT_ID'),
                    'client_secret' => fn () => kart_env('PAYPAL_CLIENT_SECRET'),
                    'checkout_options' => function (Kart $kart) {
                        // configure the checkout based on current kart instance
                        // https://developer.paypal.com/docs/api/orders/v2/#orders_create
                        return [];
                    },
                    'checkout_line' => function (Kart $kart, CartLine $line) {
                        // add custom data to the current checkout line
                        return [];
                    },
                    'virtual' => ['raw', 'title', 'description', 'gallery'],
                ],
                'shopify' => [],
                'square' => [
                    'access_token' => fn () => kart_env('SQUARE_ACCESS_TOKEN'),
                    'location_id' => fn () => kart_env('SQUARE_LOCATION_ID'),
                    'api_version' => fn () => kart_env('SQUARE_API_VERSION'), // null = default to current or set string with https://developer.squareup.com/docs/build-basics/versioning-overview
                    'checkout_options' => function (Kart $kart) {
                        // configure the checkout based on current kart instance
                        // https://developer.squareup.com/reference/square/checkout-api/create-payment-link
                        return [];
                    },
                    'checkout_line' => function (Kart $kart, CartLine $line) {
                        // add custom data to the current checkout line
                        // https://developer.squareup.com/docs/orders-api/create-orders#create-an-ad-hoc-line-item
                        return [];
                    },
                    'virtual' => false,
                ],
                'snipcart' => [
                    'public_key' => fn () => kart_env('SNIPCART_PUBLIC_KEY'),
                    'secret_key' => fn () => kart_env('SNIPCART_SECRET_KEY'),
                    'virtual' => false,
                ],
                'stripe' => [
                    'secret_key' => fn () => kart_env('STRIPE_SECRET_KEY'),
                    'checkout_options' => function (Kart $kart) {
                        // configure the checkout based on current kart instance
                        // https://docs.stripe.com/api/checkout/sessions/create
                        return [];
                    },
                    'checkout_line' => function (Kart $kart, CartLine $line) {
                        // add custom data to the current checkout line
                        return [];
                    },
                    'virtual' => ['raw', 'description', 'gallery', 'downloads', 'price', 'tags', 'categories', 'variants', 'title', 'featured'],
                ],
                'sumup' => [
                    'public_key' => fn () => kart_env('SUMUP_PUBLIC_KEY'),
                    'secret_key' => fn () => kart_env('SUMUP_SECRET_KEY'),
                    'merchant_code' => fn () => kart_env('SUMUP_MERCHANT_CODE'),
                    'checkout_options' => function (Kart $kart) {
                        // configure the checkout based on current kart instance
                        // https://developer.sumup.com/api/checkouts/create
                        return [];
                    },
                    'checkout_line' => function (Kart $kart, CartLine $line) {
                        // add custom data to the current checkout line
                        return [];
                    },
                    'virtual' => false,
                ],
            ],
            'turnstile' => [
                'endpoint' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                'sitekey' => fn () => kart_env('TURNSTILE_SITE_KEY'),
                'secretkey' => fn () => kart_env('TURNSTILE_SECRET_KEY'),
            ],
            'captcha' => [
                'enabled' => false,
                'current' => function () {
                    return get('captcha'); // in current request
                },
                'set' => function (bool $inline = true) {
                    // https://github.com/S1SYPHOS/php-simple-captcha
                    $builder = new CaptchaBuilder;
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
            'kerbs' => [
                'version' => fn () => Kart::hash(kirby()->plugin('bnomei/kart')?->version() ?? __FILE__),
                'kart' => function (): array {
                    return kart()->toKerbs();
                },
                'shop' => function (): array {
                    return [
                        'categories' => kart()->categories()->values(),
                        'products' => kart()->products()->values(fn (ProductPage $product) => $product->toKerbs(full: false)),
                        'tags' => kart()->tags()->values(),
                    ];
                },
                'site' => function (): array {
                    return kirby()->site()->toKerbs(); // @phpstan-ignore-line
                },
                'user' => function (): ?array {
                    return kirby()->user()?->toKerbs();
                },
                'i18n' => function (): array {
                    return array_filter(A::get(kirby()->translation(kirby()->language()?->code() ?? 'en')->toArray()['data'], [
                        // KIRBY
                        'back',
                        'cancel',
                        'close',
                        'confirm',
                        'download',
                        'edit',
                        'email',
                        'email.placeholder',
                        'error',
                        'info',
                        'login',
                        'logout',
                        'menu',
                        'more',
                        'name',
                        'no',
                        'off',
                        'on',
                        'password',
                        'save',
                        'saved',
                        'search',
                        'searching',
                        'title',
                        'url',
                        'user',
                        'welcome',
                        'yes',
                        // KART
                        'bnomei.kart.cart',
                        'bnomei.kart.categories',
                        'bnomei.kart.checkout',
                        'bnomei.kart.discount',
                        'bnomei.kart.in-stock',
                        'bnomei.kart.invoice',
                        'bnomei.kart.invoiceNumber',
                        'bnomei.kart.items',
                        'bnomei.kart.order',
                        'bnomei.kart.orders',
                        'bnomei.kart.out-of-stock',
                        'bnomei.kart.paidDate',
                        'bnomei.kart.price',
                        'bnomei.kart.quantity',
                        'bnomei.kart.signup',
                        'bnomei.kart.subtotal',
                        'bnomei.kart.summary',
                        'bnomei.kart.tags',
                        'bnomei.kart.tax',
                        'bnomei.kart.total',
                        'bnomei.kart.variant',
                        'bnomei.kart.variants',
                        // KERBS
                        'bnomei.kerbs.add-to-cart',
                        'bnomei.kerbs.all',
                        'bnomei.kerbs.checkout-disclaimer',
                        'bnomei.kerbs.delete-from-cart',
                        'bnomei.kerbs.featured-only',
                        'bnomei.kerbs.include-out-of-stock',
                        'bnomei.kerbs.related',
                        'bnomei.kerbs.save-for-later',
                        'bnomei.kerbs.sort-by-lowest-price',
                        'bnomei.kerbs.sort-by-rrp-percent-desc',
                    ]));
                },
            ],
        ],
        'cacheTypes' => [
            'kart-uuid' => UuidCache::class,
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
            'kart/snipcart-checkout' => __DIR__.'/snippets/kart/snipcart-checkout.php',
            'kart/sumup-checkout' => __DIR__.'/snippets/kart/sumup-checkout.php',
            'kart/turnstile-form' => __DIR__.'/snippets/kart/turnstile-form.php',
            'kart/turnstile-widget' => __DIR__.'/snippets/kart/turnstile-widget.php',
            'kart/wish-or-forget' => __DIR__.'/snippets/kart/wish-or-forget.php',
            'kart/wish-or-forget.htmx' => __DIR__.'/snippets/kart/wish-or-forget.htmx.php',
            'kart/wishlist' => __DIR__.'/snippets/kart/wishlist.php',
            'kerbs/inertia' => __DIR__.'/snippets/kerbs/inertia.php',
            'kerbs/layout' => __DIR__.'/snippets/kerbs/layout.php',
            'seo/head' => __DIR__.'/snippets/seo/head.php',
            'seo/schemas' => __DIR__.'/snippets/seo/schemas.php',
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
            'nl' => require_once __DIR__.'/translations/nl.php',
            'ru' => require_once __DIR__.'/translations/ru.php',
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
            'tabs/product-local' => __DIR__.'/blueprints/tabs/product-local.yml',
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
                kirby()->cache('bnomei.kart.gravatar')->remove(md5(strtolower(trim($user->email() ?? $user->id()))));
            },
            'page.created:after' => function (Page $page) {
                if ($page instanceof StocksPage) {
                    kirby()->cache('bnomei.kart.stocks')->flush();
                    kirby()->cache('bnomei.kart.stocks-holds')->flush();
                } elseif ($page instanceof OrderPage || $page instanceof OrdersPage) {
                    kirby()->cache('bnomei.kart.licenses')->flush();
                    kirby()->cache('bnomei.kart.orders')->flush();
                } elseif ($page instanceof ProductPage || $page instanceof ProductsPage) {
                    kirby()->cache('bnomei.kart.categories')->flush();
                    kirby()->cache('bnomei.kart.products')->flush();
                    kirby()->cache('bnomei.kart.tags')->flush();
                    kirby()->cache('bnomei.kart.stocks')->flush();
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
                    kirby()->cache('bnomei.kart.licenses')->flush();
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
                if ($page instanceof StockPage || $page instanceof StocksPage) {
                    kirby()->cache('bnomei.kart.stocks')->flush();
                } elseif ($page instanceof OrderPage || $page instanceof OrdersPage) {
                    kirby()->cache('bnomei.kart.licenses')->flush();
                    kirby()->cache('bnomei.kart.orders')->flush();
                } elseif ($page instanceof ProductPage || $page instanceof ProductsPage) {
                    kirby()->cache('bnomei.kart.categories')->flush();
                    kirby()->cache('bnomei.kart.products')->flush();
                    kirby()->cache('bnomei.kart.tags')->flush();
                    // kirby()->cache('bnomei.kart.stocks')->flush(); // can safely be ignored
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
            /**
             * @kql-allowed
             */
            'ecco' => function ($field, string $a, string $b = ''): string {
                if ($field->isEmpty()) {
                    return $b;
                }

                return empty($field->value()) || strtolower($field->value()) === 'false' ? $b : $a;
            },
            'toKerbs' => function (Field $field, ?string $type = null): array {
                if ($type === 'layouts') {
                    return $field->toLayouts()->values(function (Layout $layout) {
                        return [
                            'id' => $layout->id(),
                            'columns' => $layout->columns()->values(function (LayoutColumn $column) {
                                return [
                                    'span' => $column->span(),
                                    'blocks' => $column->blocks()->toKerbs(), // @phpstan-ignore-line
                                ];
                            }),
                        ];
                    });
                } elseif ($type === 'blocks') {
                    return $field->toBlocks()->toKerbs();
                }

                return [];
            },
        ],
        'blocksMethods' => [
            'toKerbs' => function (): array {
                return $this->values(fn (Block $block) => [
                    'id' => $block->id(),
                    'type' => $block->type(),
                    'html' => $block->toHtml(),
                ]);
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
            'toKerbs' => function (): array {
                return array_filter([
                    'alt' => $this->alt()->value(),
                    'blur' => $this->thumb('blurred')->url(),
                    'caption' => $this->caption()->kti()->value(),
                    'name' => $this->name(),
                    'ratio' => $this->ratio(),
                    'srcset' => $this->srcset('default'),
                    'thumb' => $this->thumb('default')->url(),
                    // 'url' => $this->url(),
                ]);
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
            'toKerbs' => function (): array {
                return array_filter([
                    'title' => $this->title()->value(),
                    'url' => $this->url(),
                    'layouts' => $this->layout()->or($this->layouts())->toKerbs('layouts'),
                    'blocks' => $this->blocks()->isEmpty() ? null : $this->blocks()->toKerbs('blocks'),
                ]);
            },
        ],
        'siteMethods' => [
            /**
             * @kql-allowed
             */
            'kart' => function (): Kart {
                return kart();
            },
            'toKerbs' => function (): array {
                /** @var Site $site */
                $site = $this;
                $page = $site->page();
                // if has https://github.com/tobimori/kirby-seo
                $metadata = $page?->metadata(); // @phpstan-ignore-line
                if (is_object($metadata) && is_a($metadata, '\\tobimori\\Seo\\Meta')) {
                    $metadata = $metadata->metaArray(); // @phpstan-ignore-line
                }
                // else sane defaults
                if ($page && $metadata instanceof Field) {
                    $metadata = [
                        'title' => $page?->isHomePage() ? $site->title()->value() : $page->title().' | '.$site->title(),  // @phpstan-ignore-line
                        'description' => Str::esc($page->description()->kti()), // @phpstan-ignore-line
                    ];
                }

                return array_filter([
                    'title' => $site->title()->value(), // @phpstan-ignore-line
                    'url' => $site->url(),
                    'logo' => svg(kirby()->roots()->assets().'/logo.svg') ?: '[missing /assets/logo.svg]', // @phpstan-ignore-line
                    'meta' => is_array($metadata) ? $metadata : [],
                    'listed' => $site->children()->listed()->values(fn (Page $p) => $p->toKerbs()), // @phpstan-ignore-line
                    'copyright' => $site->copyright()->isNotEmpty() ? $this->copyright()->kti()->value() : null, // @phpstan-ignore-line
                ]);
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
            'toKerbs' => function (): array {
                return array_filter([
                    'email' => $this->email(),
                    'gravatar' => $this->gravatar(64),
                    'logout' => kart()->urls()->logout(),
                    'name' => $this->name()->value(),
                    'orders' => kart()->ordersWithCustomer($this)->values(fn (OrderPage $product) => $product->toKerbs()),
                    'url' => url(Router::ACCOUNT),
                ]);
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
                /** @var User $user */
                $user = $this;
                $hash = md5(strtolower(trim($user->email() ?? $user->id())));
                $url = "https://www.gravatar.com/avatar/{$hash}?s={$size}";

                if ($cache = kirby()->cache('bnomei.kart.gravatar')->get($hash)) {
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
                    // using user for cache id to only have one and better GC
                    kirby()->cache('bnomei.kart.gravatar')->set($hash, $dataUrl, 60 * 24);

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
                $code = MagicLinkChallenge::create($user, [
                    'mode' => 'login',
                    'timeout' => 10 * 60,
                    'email' => $user->email(),
                    'name' => $user->nameOrEmail(),
                    'success_url' => $success_url ?? url('/'),
                ]);
                if ($code) {
                    kirby()->session()->set('kirby.challenge.type', 'login');
                    kirby()->session()->set('kirby.challenge.code', password_hash($code, PASSWORD_DEFAULT));
                }
            },
        ],
        'fields' => [
            'allcategories' => [
                'extends' => 'tags',
                'props' => [
                    'value' => function () {
                        return kart()->allCategories();
                    },
                ],
            ],
            'alltags' => [
                'extends' => 'tags',
                'props' => [
                    'value' => function () {
                        return kart()->allTags();
                    },
                ],
            ],
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
