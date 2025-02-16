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
            'ordersPage' => 'orders',
            'productsPage' => 'products',
            'stocksPage' => 'stocks',
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
        'snippets' => [
            'kart/html/cart' => __DIR__.'/snippets/kart/html/cart.php',
            'kart/html/add' => __DIR__.'/snippets/kart/html/add.php',
            'kart/html/login' => __DIR__.'/snippets/kart/html/login.php',
            'kart/html/logout' => __DIR__.'/snippets/kart/html/logout.php',
            'kart/html/wishlist' => __DIR__.'/snippets/kart/html/wishlist.php',
            'kart/html/wish' => __DIR__.'/snippets/kart/html/wish-or-forget.php',
            'kart/html/forget' => __DIR__.'/snippets/kart/html/forget.php',
        ],
        'routes' => require_once __DIR__.'/routes.php',
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
