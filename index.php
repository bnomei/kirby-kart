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
            'pages/order' => OrderPage::phpBlueprint(),
            'pages/orders' => OrdersPage::phpBlueprint(),
            'pages/product' => ProductPage::phpBlueprint(),
            'pages/products' => ProductsPage::phpBlueprint(),
            'pages/stocks' => StocksPage::phpBlueprint(),
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
            'system.loadPlugins:after' => function (): void {
                // make sure the kart singleton is ready in calling it once
                kart();
            },
            'page.create:after' => function (\Kirby\Cms\Page $page): void {
                if ($page instanceof OrderPage) {
                    $page->updateInvoiceNumber();
                }
            },
            'page.update:before' => function (\Kirby\Cms\Page $page, array $values, array $strings): void {
                if ($page instanceof StocksPage) {
                    if (! $page->onlyUniqueProducts(A::get($values, 'stocks', []))) {
                        throw new \Exception(t('kart.stocks.exception.uniqueness', 'Stocks must contain unique products'));
                    }
                }
            },
            'page.update:after' => function (\Kirby\Cms\Page $newPage, \Kirby\Cms\Page $oldPage): void {
                if ($newPage instanceof OrderPage) {
                    $newPage->updateInvoiceNumber();
                }
            },
        ],
        'siteMethods' => [
            'kart' => function (): \Bnomei\Kart\Kart {
                return kart();
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
