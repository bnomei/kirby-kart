<?php

use Bnomei\Kart\Helper;
use Bnomei\Kart\Kart;
use Bnomei\Kart\License;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Content\Field;
use Kirby\Toolkit\A;

@include_once __DIR__.'/vendor/autoload.php';

if (! function_exists('kart')) {
    function kart(): Kart
    {
        return Kart::singleton();
    }
}

Kirby::plugin(
    name: 'bnomei/kart',
    license: fn ($plugin) => new License($plugin, License::NAME),
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

            'locale' => 'en_EN', // or current locale on multilanguage setups
            'currency' => 'EUR',
            'orders' => [
                'enabled' => true, // true|false, 'dreamform'
                'page' => 'orders', // 'orders' or point to root of dreamform
                'slug' => fn (OrdersPage $orders, array $props) => Helper::uuid(5), // aka order id
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
            'provider' => \Bnomei\Kart\Provider\Kirby::class, // stripe, mollie, paddle, ...
            'providers' => [
                'stripe' => [
                    'secret_key' => fn () => env('STRIPE_SECRET_KEY'),
                ],
                'mollie' => [],
                'paddle' => [],
            ],
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
            'kart/json-ld/product' => __DIR__.'/snippets/kart/json-ld/product.php',
            'kart/login' => __DIR__.'/snippets/kart/login.php',
            'kart/logout' => __DIR__.'/snippets/kart/logout.php',
            'kart/html/cart' => __DIR__.'/snippets/kart/html/cart.php',
            'kart/html/add' => __DIR__.'/snippets/kart/html/add.php',
            'kart/html/wishlist' => __DIR__.'/snippets/kart/html/wishlist.php',
            'kart/html/wish-or-forget' => __DIR__.'/snippets/kart/html/wish-or-forget.php',
        ],
        'routes' => require_once __DIR__.'/routes.php',
        'translations' => [
            'de' => require_once __DIR__.'/translations/de.php',
            'en' => require_once __DIR__.'/translations/en.php',
            'fr' => require_once __DIR__.'/translations/fr.php',
            'it' => require_once __DIR__.'/translations/it.php',
        ],
        'hooks' => [
            'system.loadPlugins:after' => function (): void {
                // make sure the kart singleton is ready in calling it once
                kart();
            },
            'user.login:after' => function (Kirby\Cms\User $user, Kirby\Session\Session $session) {
                kart()->cart()->merge($user);
                kart()->wishlist()->merge($user);
            },
            'user.logout:after' => function (Kirby\Cms\User $user, Kirby\Session\Session $session) {
                kart()->cart()->clear();
                kart()->wishlist()->clear();
            },
            'page.create:after' => function (Page $page): void {
                if ($page instanceof OrderPage) {
                    $page->updateInvoiceNumber();
                }
            },
            'page.update:before' => function (Page $page, array $values, array $strings): void {
                if ($page instanceof StocksPage) {
                    if (! $page->onlyUniqueProducts(A::get($values, 'stocks', []))) {
                        throw new Exception(t('kart.stocks.exception.uniqueness', 'Stocks must contain unique products'));
                    }
                }
            },
            'page.update:after' => function (Page $newPage, Page $oldPage): void {
                //                if ($newPage instanceof OrderPage) {
                //                    $newPage->updateInvoiceNumber();
                //                }
            },
        ],
        'siteMethods' => [
            'kart' => function (): Kart {
                return kart();
            },
        ],
        'userMethods' => [
            'orders' => function (): ?Pages {
                return kart()->page('orders')?->children()->filterBy(fn ($order) => $order->customer()->toUser()?->id() === $this->id());
            },
            'hasMadePaymentFor' => function (string $provider, ProductPage $productPage): bool {
                if ($this->$provider()->isEmpty()) {
                    return false;
                }
                // try finding a payment like KLUB would store it on fulfillment of one_time purchases
                // which is stripe.payments[<array of price_ids>]
                $data = $this->$provider()->yaml();

                return count(array_intersect(
                    $productPage->priceIds(),
                    A::get($data, 'payments', [])
                ));
            },
        ],
        'fieldMethods' => [
            'toFormattedNumber' => function ($field): string {
                $field->value = Helper::formatNumber(floatval($field->value));

                return $field;
            },
            'toFormattedCurrency' => function (Field $field): string {
                $field->value = Helper::formatCurrency(floatval($field->value));

                return $field;
            },
        ],
        'pagesMethods' => [
            'sum' => function (string $field): float|int {
                return array_sum($this->toArray(function ($i) use ($field) {
                    if (property_exists($i, $field)) {
                        return $i;
                    }
                    $f = $i->$field();
                    if ($f instanceof Field) {
                        return $i->toFloat();
                    }

                    return is_numeric($f) ? $f : 0;
                }));
            },
            'sumField' => function (string $field): Field {
                return new Field(null, $field, $this->sum($field));
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
    ]);
