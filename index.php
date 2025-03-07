<?php

use Bnomei\Kart\Cart;
use Bnomei\Kart\CartLine;
use Bnomei\Kart\Kart;
use Bnomei\Kart\License;
use Bnomei\Kart\OrderLine;
use Bnomei\Kart\Router;
use Kirby\Cms\App;
use Kirby\Cms\Collection;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\User;
use Kirby\Content\Field;
use Kirby\Data\Yaml;
use Kirby\Http\Remote;
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
                'gravatar' => true,

                // providers
                'fastspring' => true,
                'gumroad' => true,
                'invoice_ninja' => true,
                'kirby_cms' => true,
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
                    'uuid' => fn (?OrdersPage $orders, array $props) => 'or-'.Kart::nonAmbiguousUuid(7), // aka order id
                    'create-missing-zips' => true,
                ],
            ],
            'products' => [
                'enabled' => true,
                'page' => 'products',
                'product' => [
                    'uuid' => fn (?ProductsPage $products, array $props) => 'pr-'.Kart::hash(A::get($props, 'id')),
                ],
            ],
            'stocks' => [
                'enabled' => true,
                'page' => 'stocks',
                'stock' => [
                    'uuid' => fn (?StocksPage $stocks, array $props) => 'st-'.Kart::nonAmbiguousUuid(13),
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
            'provider' => 'kirby_cms', // see ProviderEnum (kirby_cms, stripe, mollie, paddle, ...) or use \Kart\Provider\Kirby::class etc.
            'providers' => [
                'fastspring' => [],
                'gumroad' => [],
                'invoice_ninja' => [],
                'kirby_cms' => [
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
            'kart/add' => __DIR__.'/snippets/add.php',
            'kart/buy' => __DIR__.'/snippets/buy.php',
            'kart/cart' => __DIR__.'/snippets/cart.php',
            'kart/input/csrf' => __DIR__.'/snippets/input-csrf.php',
            'kart/input/csrf-defer' => __DIR__.'/snippets/input-csrf-defer.php',
            'kart/json-ld/product' => __DIR__.'/snippets/json-ld-product.php',
            'kart/login' => __DIR__.'/snippets/login.php',
            'kart/logout' => __DIR__.'/snippets/logout.php',
            'kart/profile' => __DIR__.'/snippets/profile.php',
            'kart/wish-or-forget' => __DIR__.'/snippets/wish-or-forget.php',
            'kart/wishlist' => __DIR__.'/snippets/wishlist.php',
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
        'templates' => [
            'order' => __DIR__.'/templates/order.php',
            'order.pdf' => __DIR__.'/templates/order.pdf.php',
            'order.zip' => __DIR__.'/templates/order.zip.php',
            'orders' => __DIR__.'/templates/orders.php',
            'payment' => __DIR__.'/templates/payment.php',
            'product' => __DIR__.'/templates/product.php',
            'products' => __DIR__.'/templates/products.php',
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
                return kart()->categories()->filterBy('value', 'in', explode(',', $field->value));
            },
            /**
             * @kql-allowed
             */
            'toTags' => function (Field $field): Collection {
                return kart()->tags()->filterBy('value', 'in', explode(',', $field->value));
            },
            /**
             * @kql-allowed
             */
            'toCartLines' => function (Field $field): Collection {
                $lines = [];
                foreach ($field->toStructure() as $line) {
                    $lines[] = new CartLine($line->id(), $line->quanity());
                }

                return new Collection($lines);
            },
            /**
             * @kql-allowed
             */
            'toOrderLines' => function (Field $field): Collection {
                $lines = [];
                foreach ($field->toStructure() as $line) {
                    $lines[] = new OrderLine(
                        $line->id(),
                        $line->price()->toFloat(),
                        $line->quantity()->toInt(),
                        $line->total()->toFloat(),
                        $line->subtotal()->toFloat(),
                        $line->tax()->toFloat(),
                        $line->discount()->toFloat()
                    );
                }

                return new Collection($lines);
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
            /* DANGER: do not do this... this is a field
            'cart' => function (): Cart { },
            */
            /* DANGER: do not do this... this is a field
            'wishlist' => function (): Cart { },
            */
            /**
             * @kql-allowed
             */
            'orders' => function (): ?Pages {
                return kart()->orders()
                    ->filterBy(fn (OrderPage $order) => $order->customer()->toUser()?->id() === $this->id())
                    ->sortBy('paidDate', 'desc');
            },
            /**
             * @kql-allowed
             */
            'completedOrders' => function (): Pages {
                /** @var CustomerUser $this */
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
                    $imageInfo = getimagesizefromstring($image) ?: [];
                    $mimeType = A::get($imageInfo, 'mime', 'image/png');
                    $base64 = base64_encode($image);
                    $dataUrl = "data:{$mimeType};base64,{$base64}";

                    kirby()->cache('bnomei.kart.gravatar')->set(md5($url), $dataUrl, 60 * 24);

                    return $dataUrl;
                }

                return $url;
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
