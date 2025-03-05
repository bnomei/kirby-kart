<?php

use Bnomei\Kart\Helper;
use Bnomei\Kart\Kart;
use Bnomei\Kart\License;
use Bnomei\Kart\Router;
use Kirby\Cms\App;
use Kirby\Cms\Collection;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\User;
use Kirby\Content\Field;
use Kirby\Data\Yaml;
use Kirby\Session\Session;
use Kirby\Toolkit\A;

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
            'license' => '', // set your license from https://buy-kart.bnomei.com code in the config `bnomei.kart.license`
            'cache' => [
                'ratelimit' => true,

                // providers
                'fastspring' => true,
                'gumroad' => true,
                'invoiceninja' => true,
                'kirbycms' => true,
                'lemonsqueeze' => true,
                'mollie' => true,
                'paddle' => true,
                'payone' => true,
                'paypal' => true,
                'snipcart' => true,
                'stripe' => true,
            ],
            'expire' => 0, // 0 = forever, null to disable caching
            'customers' => [
                'enabled' => true,
                'roles' => ['customer', 'member', 'admin'],
            ],
            'locale' => 'en_EN', // or current locale on multilanguage setups
            'currency' => 'EUR', // uppercase 3-letter code
            'successPage' => null, // id of the page to redirect to after checkout flow, defaults to page of order
            'orders' => [
                'enabled' => true,
                'page' => 'orders',
                'order' => [
                    'uuid' => fn (?OrdersPage $orders, array $props) => 'or-'.Helper::nonAmbiguousUuid(7), // aka order id
                ],
            ],
            'products' => [
                'enabled' => true,
                'page' => 'products',
                'product' => [
                    'uuid' => fn (?ProductsPage $products, array $props) => 'pr-'.Helper::hash(A::get($props, 'id')),
                ],
            ],
            'stocks' => [
                'enabled' => true,
                'page' => 'stocks',
                'stock' => [
                    'uuid' => fn (?StocksPage $stocks, array $props) => 'st-'.Helper::nonAmbiguousUuid(13),
                ],
            ],
            'router' => [
                'mode' => 'go', // go/json/html
                'encryption' => fn () => sha1(__DIR__), // or false
                'csrf' => 'token', // null|false or name of form data
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
                        Router::class.'::csrf',
                        Router::class.'::ratelimit',
                        // ... static class::method or closures
                    ];
                },
            ],
            'provider' => 'kirbycms', // see ProviderEnum (kirbycms, stripe, mollie, paddle, ...) or use \Kart\Provider\Kirby::class etc.
            'providers' => [
                'fastspring' => [],
                'gumroad' => [],
                'invoiceninja' => [],
                'kirbycms' => [
                    'virtual' => false,
                ],
                'lemonsqueeze' => [],
                'mollie' => [],
                'paddle' => [],
                'payone' => [],
                'paypal' => [],
                'snipcart' => [],
                'stripe' => [
                    'secret_key' => fn () => env('STRIPE_SECRET_KEY'),
                    'checkout_options' => function (Kart $kart) {
                        // configure the checkout based on current kart instance
                        // https://docs.stripe.com/api/checkout/sessions/create
                        return [];
                    },
                    'virtual' => 'prune', // 'prune', // do not write virtual fields to file
                ],
            ],
        ],
        'routes' => require_once __DIR__.'/routes.php',
        'snippets' => [
            'kart/input/csrf' => __DIR__.'/snippets/kart/input/csrf.php',
            'kart/input/csrf-defer' => __DIR__.'/snippets/kart/input/csrf-defer.php',
            'kart/json-ld/product' => __DIR__.'/snippets/kart/json-ld/product.php',
            'kart/login' => __DIR__.'/snippets/kart/login.php',
            'kart/logout' => __DIR__.'/snippets/kart/logout.php',
            'kart/html/cart' => __DIR__.'/snippets/kart/html/cart.php',
            'kart/html/add' => __DIR__.'/snippets/kart/html/add.php',
            'kart/html/buy' => __DIR__.'/snippets/kart/html/buy.php',
            'kart/html/wishlist' => __DIR__.'/snippets/kart/html/wishlist.php',
            'kart/html/wish-or-forget' => __DIR__.'/snippets/kart/html/wish-or-forget.php',
        ],
        'translations' => [
            'en' => require_once __DIR__.'/translations/en.php',
            'de' => require_once __DIR__.'/translations/de.php',
        ],
        'blueprints' => [
            'users/customer' => CustomerUser::phpBlueprint(),
            'pages/order' => OrderPage::phpBlueprint(),
            'pages/orders' => OrdersPage::phpBlueprint(),
            'pages/product' => ProductPage::phpBlueprint(),
            'pages/products' => ProductsPage::phpBlueprint(),
            'pages/stock' => StockPage::phpBlueprint(),
            'pages/stocks' => StocksPage::phpBlueprint(),
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
                kart()->wishlist()->merge($user);
            },
            'user.logout:after' => function (User $user, Session $session) {
                kart()->cart()->clear();
                kart()->wishlist()->clear();
            },
            'page.update:before' => function (Page $page, array $values, array $strings): void {
                if ($page instanceof StockPage) {
                    if (! $page->onlyOneStockPagePerProduct($values)) {
                        throw new Exception(strval(t('bnomei.kart.stocks.exception-uniqueness')));
                    }
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
        ],
        'fieldMethods' => [
            /**
             * @kql-allowed
             */
            'toFormattedNumber' => function ($field, bool $prefix = false): string {
                $field->value = Helper::formatNumber(floatval($field->value), $prefix);

                return $field;
            },
            /**
             * @kql-allowed
             */
            'toFormattedCurrency' => function (Field $field): string {
                $field->value = Helper::formatCurrency(floatval($field->value));

                return $field;
            },
            /**
             * @kql-allowed
             */
            'toCategories' => function (Field $field): Collection {
                return kart()->categories()->filterBy('value', $field->value);
            },
            /**
             * @kql-allowed
             */
            'toTags' => function (Field $field): Collection {
                return kart()->tags()->filterBy('value', $field->value);
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
        'pageMethods' => [
            /**
             * @kql-allowed
             */
            'kart' => function (): Kart {
                return kart();
            },
            'dump' => function (?string $field = null, int $maxWidth = 140): string {
                $content = $this->content->toArray();
                if ($field) {
                    $content = A::get($content, $field, []);
                    try { // if the field is a yaml/json content
                        $content = is_string($content) ? Yaml::decode($content) : $content;
                        $content = is_string($content) && json_decode($content) ? json_decode($content) : $content;
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
            'orders' => function (): ?Pages {
                return kart()->orders()
                    ->filterBy(fn ($order) => $order->customer()->toUser()?->id() === $this->id());
            },
            /**
             * @kql-allowed
             */
            'completedOrders' => function (): Pages {
                /** @var CustomerUser $this */
                return $this->orders()
                    ->filterBy(fn ($order) => $order->paymentComplete()->toBool());
            },
            /**
             * @kql-allowed
             */
            'hasMadePaymentFor' => function (string $provider, ProductPage $productPage): bool {
                /** @var CustomerUser $this */
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
                        Yaml::write(__DIR__."/blueprints/{$name}.yml", $class::phpBlueprint()); // @phpstan-ignore-line
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
