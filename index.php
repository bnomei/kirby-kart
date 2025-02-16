<?php

@include_once __DIR__.'/vendor/autoload.php';

if (! function_exists('kart')) {
    function kart(): \Bnomei\Kart\Kart
    {
        return \Bnomei\Kart\Kart::singleton();
    }
}

Kirby::plugin(
    name: 'bnomei/kart',
    license: fn ($plugin) => new \Bnomei\Kart\License($plugin, \Bnomei\Kart\License::NAME),
    extends: [
        'options' => [
            'license' => '', // set your license from https://buy-kart.bnomei.com code in the config `bnomei.kart.license`
            'cache' => [
                'ratelimit' => true,
                'stripe' => true,
                'mollie' => true,
                'paddle' => true,
            ],
            'expire' => 0, // 0 = forever, null to disable caching
            'provider' => 'kirby', // stripe, mollie, paddle, ...
            'locale' => 'en_EN', // or current locale on multilanguage setups
            'currency' => 'EUR',
            'orders' => [
                'enabled' => true, // true|false, 'dreamform'
                'page' => 'orders', // 'orders' or point to root of dreamform
                'slug' => fn (OrdersPage $orders, array $props) => \Bnomei\Kart\Data::uuid(5), // aka order id
            ],
            'products' => [
                'page' => 'products',
            ],
            'stocks' => [
                'enabled' => true,
                'page' => 'stocks',
            ],
            'csrf' => [
                'enabled' => true,
            ],
            'ratelimit' => [
                'enabled' => true,
                'limit' => 30 * 60, // N requests in 60 seconds
            ],
            'stripe' => [
                'secret_key' => fn () => env('STRIPE_SECRET_KEY'),
            ],
            'mollie' => [],
            'paddle' => [],
        ],
        'blueprints' => [
            'users/customer' => require_once __DIR__.'/blueprints/users/customer.php',
            'page/order' => OrderPage::phpBlueprint(),
            'page/orders' => OrdersPage::phpBlueprint(),
            'page/product' => ProductPage::phpBlueprint(),
            'page/products' => ProductsPage::phpBlueprint(),
            'page/stocks' => StocksPage::phpBlueprint(),
        ],
        'pageModels' => [
            'order' => OrderPage::class,
            'orders' => OrdersPage::class,
            'product' => ProductPage::class,
            'products' => ProductsPage::class,
            'stocks' => StocksPage::class,
        ],
        'snippets' => [
            'kart/login' => __DIR__.'/snippets/kart/login.php',
            'kart/logout' => __DIR__.'/snippets/kart/logout.php',
            'kart/html/cart' => __DIR__.'/snippets/kart/html/cart.php',
            'kart/html/add' => __DIR__.'/snippets/kart/html/add.php',
            'kart/html/wishlist' => __DIR__.'/snippets/kart/html/wishlist.php',
            'kart/html/wish-or-forget' => __DIR__.'/snippets/kart/html/wish-or-forget.php',
        ],
        'routes' => require_once __DIR__.'/routes.php',
        'hooks' => [
            'system.loadPlugins:after' => function () {
                // make sure the kart singleton is ready in calling it once
                kart();
            },
        ],
        'commands' => [
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
                    \Bnomei\Kart\Kart::flush($name);
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
    ]);
