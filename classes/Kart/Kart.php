<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Bnomei\Kart\Mixins\ContentPages;
use Bnomei\Kart\Mixins\Options;
use Bnomei\Kart\Mixins\Stats;
use Bnomei\Kart\Mixins\TMNT;
use Bnomei\Kart\Provider\Kirby;
use Closure;
use Exception;
use Kirby\Cms\App;
use Kirby\Cms\Collection;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\Site;
use Kirby\Cms\User;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use Kirby\Toolkit\SymmetricCrypto;
use Kirby\Toolkit\V;
use Kirby\Uuid\Uuid;
use NumberFormatter;
use OrderPage;
use ProductPage;

class Kart implements Kerbs
{
    use ContentPages;
    use Options;
    use Stats;
    use TMNT;

    private static ?Kart $singleton = null;

    private ?Provider $provider;

    private ?Cart $cart;

    private ?Wishlist $wishlist;

    private App $kirby;

    private Licenses $licenses;

    private Urls $urls;

    private Queue $queue;

    public function __construct()
    {
        $this->cart = null; // lazy
        $this->kirby = kirby();
        $this->provider = null;  // lazy
        $this->licenses = new Licenses;
        $this->queue = new Queue;
        $this->urls = new Urls;
        $this->wishlist = null;  // lazy
    }

    public static function singleton(): Kart
    {
        if (self::$singleton === null) {
            self::$singleton = new self;
            self::$singleton->ready();
        }

        return self::$singleton;
    }

    public function ready(): void
    {
        $this->makeContentPages();
        $this->queue()->process();

        // Clean up caches that have no GC itself
        if (rand(1, 200) === 1) {
            static::flush('crypto');
            Ratelimit::flush();
        }

        if (sha1(file_get_contents(__DIR__.strrev(base64_decode('cGhwLmVzbmVjaUwv')))) !== 'c96b2a082835cbfa95c2bc27f669098dd340bd5e' && $kart = base64_decode('c2xlZXA=')) { // @phpstan-ignore-line
            $kart(5); // @phpstan-ignore-line
        }
    }

    public function queue(): Queue
    {
        return $this->queue;

    }

    public function licenses(): Licenses
    {
        return $this->licenses;

    }

    public static function flush(string $cache = 'all'): bool
    {
        if (kart()->option('expire') === null) {
            return false;
        }

        try {
            $caches = [];
            if (empty($cache) || $cache === '*' || $cache === 'all') {
                $caches = array_keys((array) kart()->option('cache'));
            } else {
                $caches[] = $cache;
            }
            foreach ($caches as $c) {
                kirby()->cache('bnomei.kart.'.$c)->flush();
            }

            return true;
        } catch (Exception) {
            // if given a cache that does not exist or is not flushable
            return false;
        }
    }

    public static function encrypt(mixed $data, ?string $password = null, bool $json = false): string
    {
        $password ??= kart()->option('crypto.password');
        if ($password instanceof Closure) {
            $password = $password();
        }
        if (! empty($password) && is_string($password) && SymmetricCrypto::isAvailable()) {
            if ($json || is_array($data)) {
                $data = json_encode($data) ?: '';
            }

            // encryption is slowish thus using a cache
            $expire = kart()->option('expire');
            if (is_int($expire)) {
                $key = Kart::hash(strval($data));
                $data = kirby()->cache('bnomei.kart.crypto')->getOrSet($key, fn () => is_string($data) ? (new SymmetricCrypto(password: $password))->encrypt($data) : $data, $expire);
            } else {
                $data = is_string($data) ? (new SymmetricCrypto(password: $password))->encrypt($data) : $data;
            }
        }

        return base64_encode(strval($data));
    }

    public static function signature(string|array $data, ?array $without = null): string
    {
        if (is_null($without)) {
            $without = ['signature', 'prg'];
        }

        if (is_string($data)) {
            $path = parse_url($data, PHP_URL_PATH);
            $query = parse_url($data, PHP_URL_QUERY);
            $data = [];
            parse_str(is_string($query) ? $query : '', $data);
            array_unshift($data, $path);
        }

        return hash_hmac(
            'sha256',
            implode('', A::without($data, $without)),
            strval(kart()->option('crypto.salt'))
        );
    }

    public static function checkSignature(string $signature, string $url, ?array $without = null): bool
    {
        return hash_equals(
            $signature,
            self::signature($url, $without)
        );
    }

    public function validateSignatureOrGo(string $redirect = '/', ?string $url = null): void
    {
        $url ??= kirby()->request()->url();
        $signature = get('signature');
        if (! is_string($signature) || self::checkSignature($signature, $url) === false) {
            go($redirect);
        }
    }

    public static function hash(string $value): string
    {
        return str_pad(hash('xxh3', $value), 16, '0', STR_PAD_LEFT);
    }

    public static function zeroPad(string $value, int $length = 3): string
    {
        return str_pad($value, $length, '0', STR_PAD_LEFT);
    }

    public static function decrypt(string $data, Closure|string|null $password = null, bool $json = false): mixed
    {
        $data = base64_decode($data);

        $password ??= kart()->option('crypto.password');
        if ($password instanceof Closure) {
            $password = $password();
        }
        if ($password && SymmetricCrypto::isAvailable()) {
            if (Str::contains($data, '"mode":"secretbox"')) {
                $expire = kart()->option('expire');
                if (is_int($expire)) {
                    $key = Kart::hash($data);
                    $data = kirby()->cache('bnomei.kart.crypto')->getOrSet($key, fn () => (new SymmetricCrypto(password: $password))->decrypt($data), $expire);
                } else {
                    $data = (new SymmetricCrypto(password: $password))->decrypt($data);
                }
            }
            if ($json) {
                $data = json_decode((string) $data, true);
            }
        }

        return $data;
    }

    public static function formatNumber(float $number, bool $prefix = false): string
    {
        if ($prefix) {
            $prefix = $number > 0 ? '+' : ''; // - will be in format anyway
        } else {
            $prefix = '';
        }

        return $prefix.self::formatter(NumberFormatter::DECIMAL)->format($number);
    }

    public static function formatter(?int $style = null): NumberFormatter
    {
        $kirby = kirby();
        $locale = $kirby->multilang() ? $kirby->language()?->locale() : null;
        if (is_array($locale)) {
            $locale = $locale[0];
        }
        if (is_null($locale)) {
            $locale = kart()->option('locale', 'en_EN');
        }

        if (! $kirby->environment()->isLocal() && $kirby->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
            $locale = 'ja_JP';
        }

        return new NumberFormatter($locale, $style ?? NumberFormatter::DECIMAL);
    }

    public static function nonAmbiguousUuid(int $length): string
    {
        return str_replace(
            ['o', 'O', 'l', 'L', 'I', 'i', 'B', 'S', 's'],
            ['0', '0', '1', '1', '1', '1', '8', '5', '5'],
            Uuid::generate($length)
        );
    }

    public static function formatCurrency(float $number): string
    {
        $kirby = kirby();
        $currency = strval(kart()->option('currency', 'EUR'));

        if (! $kirby->environment()->isLocal() && $kirby->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
            $currency = 'JPY';
        }

        return self::formatter(NumberFormatter::CURRENCY)->formatCurrency($number, $currency) ?: '';
    }

    public static function sanitize(mixed $data, bool $checkLength = true): mixed
    {
        if (! is_string($data) && ! is_array($data)) {
            return false;
        }

        // convert to json and limit amount of chars with exception
        $json = is_array($data) ? json_encode($data) : $data;
        if ($json === false) {
            return false;
        }
        if (strlen($json) > 10000) {
            return false;
        }
        $json = null; // free memory

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                // checkLength of total data was already done above
                $data[$key] = Kart::sanitize($value, checkLength: false);
            }
        } elseif (is_string($data)) {
            $data = strip_tags(trim(empty($data) ? '' : $data));
        }

        return $data;
    }

    public function wishlist(): Wishlist
    {
        if (! $this->wishlist) {
            $this->wishlist = new Wishlist('wishlist');
        }

        return $this->wishlist;
    }

    public function currency(): string
    {
        return strval($this->option('currency'));
    }

    public function checkout(): string
    {
        return $this->urls()->cart_checkout();
    }

    public function urls(): Urls
    {
        return $this->urls;
    }

    public function login(?string $email = null): string
    {
        return $this->urls()->login($email);
    }

    public function logout(): string
    {
        return $this->urls()->logout();
    }

    public function sync(Page|string|null $page = null): string
    {
        return $this->urls()->sync($page);
    }

    public function cart(): Cart
    {
        if (! $this->cart) {
            $this->cart = new Cart('cart');
        }

        return $this->cart;
    }

    public function message(?string $message = null, string $channel = 'default'): ?string
    {
        if ($message === null) {
            return $this->kirby()->session()->pull('bnomei.kart.message-'.$channel);
        }

        $this->kirby()->session()->set('bnomei.kart.message-'.$channel, $message);

        return null;
    }

    public function kirby(): App
    {
        return $this->kirby;
    }

    public function createOrUpdateCustomer(array $credentials, bool $events = true): ?User
    {
        $email = A::get($credentials, 'customer.email');
        $id = A::get($credentials, 'customer.id');

        $customer = null;
        if ($id) {
            $customer = $this->kirby()->users()->filterBy(fn ($user) =>
                // customerId to align with KLUB
                $user->isCustomer() && $user->userData('customerId') === $id)->first();
        }
        if (! $customer) {
            $customer = $this->kirby()->users()->findBy('email', $email);
        }
        if (! $customer && V::email($email) && $this->option('customers.enabled')) {
            $customer = $this->kirby()->impersonate('kirby', fn () => $this->kirby()->users()->create([
                'email' => $email,
                'name' => A::get($credentials, 'customer.name', ''),
                'password' => Str::random(16),
                'role' => ((array) $this->option('customers.roles'))[0],
            ]));
            if ($events) {
                $this->kirby()->trigger('kart.user.created', ['user' => $customer]);
            }
        }

        // update user
        $customer = $customer instanceof User ? $this->provider()->setUserData([
            'customerId' => $id, // customerId to align with KLUB
        ], $customer) : null;

        return $customer;
    }

    public function provider(): Provider
    {
        if (! $this->provider) {
            $class = strval($this->option('provider'));
            // try finding provider from string if it's not a class yet
            if (in_array(strtolower($class), array_map(fn ($i) => $i->value, ProviderEnum::cases()))) {
                $c = ucfirst(strtolower($class));
                $class = "\\Bnomei\\Kart\\Provider\\{$c}";
            }
            if (class_exists($class)) {
                $this->provider = new $class($this->kirby()); // @phpstan-ignore-line
            }
            if (! $this->provider instanceof Provider) {
                $this->provider = new Kirby($this->kirby());
            }
        }

        return $this->provider;
    }

    /**
     * @kql-allowed
     */
    public function ordersWithProduct(ProductPage|string|null $product): Pages
    {
        return $this->orders()->filterBy(
            fn (OrderPage $orderPage) => $product ? $orderPage->hasProduct($product) : false
        );
    }

    /**
     * @kql-allowed
     */
    public function orders(): Pages
    {
        return kart()->page(ContentPageEnum::ORDERS)?->children()->sortBy('paidDate', 'desc') ?: new Pages;
    }

    public function page(ContentPageEnum|string $key): ?Page
    {
        if (is_string($key)) {
            foreach (ContentPageEnum::cases() as $case) {
                if ($case->value === $key) {
                    $key = $case;
                    break;
                }
            }
        }

        if ($key instanceof ContentPageEnum) {
            $key = $key->value;
            $key = strval($this->kirby()->option("bnomei.kart.{$key}.page"));
        }

        return $this->kirby()->page($key);
    }

    /**
     * @kql-allowed
     */
    public function ordersWithCustomer(User|string|null $user = null): Pages
    {
        if (is_null($user)) {
            $user = kirby()->user();
        }

        if (is_string($user)) {
            $user = $this->kirby()->users()->findBy('email', $user);
        }

        return $this->orders()->filterBy(
            fn (OrderPage $orderPage) => $user && $orderPage->customer()->toUser()?->is($user)
        );
    }

    /**
     * @kql-allowed
     */
    public function ordersWithInvoiceNumber(int|string $invoiceNumber): OrderPage|Page|null
    {
        if (is_string($invoiceNumber)) {
            $invoiceNumber = ltrim($invoiceNumber, '0');
        }

        return $this->orders()->filterBy(
            fn (OrderPage $orderPage) => $orderPage->invnumber()->toInt() === $invoiceNumber
        )->first();
    }

    /**
     * @kql-allowed
     */
    public function productsByParams(array $params = []): Pages
    {
        $params = array_filter(array_merge($params, array_filter([
            'category' => $this->category(),
            'categories' => $this->category(true),
            'tag' => $this->tag(),
            'tags' => $this->tag(true),
        ])));

        if (empty($params)) {
            return $this->products();
        }

        $expire = kart()->option('expire');
        if (is_int($expire)) {
            $key = Kart::hash(implode(',', $params));

            return new Pages(array_filter(kirby()->cache('bnomei.kart.products')->getOrSet('products-'.$key, fn () => array_values($this->getProductsByParam($params)->toArray(fn (ProductPage $product) => $product->uuid()->toString()))), fn ($id) => $this->kirby()->page($id) !== null));
        }

        return $this->getProductsByParam($params);
    }

    /**
     * @kql-allowed
     */
    public function products(): Pages
    {
        return kart()->page(ContentPageEnum::PRODUCTS)?->children() ?: new Pages;
    }

    private function getProductsByParam(array $params = []): Pages
    {
        $products = $this->products();

        if ($category = A::get($params, 'category')) {
            $products = $products->filterBy('categories', $category, ',');
        }
        if ($categories = A::get($params, 'categories')) {
            $products = $products->filterBy('categories', 'in', explode(',', (string) $categories), ',');
        }
        if ($tag = A::get($params, 'tag')) {
            $products = $products->filterBy('tags', $tag, ',');
        }
        if ($tags = A::get($params, 'tags')) {
            $products = $products->filterBy('tags', 'in', explode(',', (string) $tags), ',');
        }

        return $products;
    }

    /**
     * @kql-allowed
     */
    public function productsWithoutStocks(): Pages
    {
        return $this->products()->filterBy(fn (ProductPage $page) => ! is_numeric($page->stock()));
    }

    /**
     * @kql-allowed
     */
    public function productsRelated(ProductPage $product): Pages
    {
        $withCategory = $this->productsWithCategory($product->categories());
        $withTag = $this->productsWithTag($product->tags());

        return $withCategory->merge($withTag);
    }

    /**
     * @kql-allowed
     */
    public function productsWithCategory(string|array $categories, bool $any = true): Pages
    {
        if (is_string($categories)) {
            $categories = [$categories];
        }
        sort($categories);
        $categories = array_unique(array_map(fn ($cat) => urldecode($cat), $categories));

        $expire = kart()->option('expire');
        if (is_int($expire)) {
            $key = 'categories-'.Kart::hash(implode(',', $categories)).($any ? '-any' : '-all');

            return new Pages(array_filter(kirby()->cache('bnomei.kart.products')->getOrSet($key, function () use ($categories, $any) {
                $products = $any ? $this->products()->filterBy('categories', 'in', $categories, ',') :
                    $this->products()->filterBy(fn ($product) => count(array_diff($categories, $product->tags()->split())) === 0);

                return array_values($products->toArray(fn (ProductPage $product) => $product->uuid()->toString()));
            }, $expire)), fn ($id) => $this->kirby()->page($id) !== null);
        }

        return $any ? $this->products()->filterBy('categories', 'in', $categories, ',') :
            $this->products()->filterBy(fn ($product) => count(array_diff($categories, $product->categories()->split())) === 0);
    }

    /**
     * @return Collection<Tag>
     */
    public function tags(?string $path = null): Collection
    {
        $expire = kart()->option('expire');
        if (is_int($expire)) {
            $key = Kart::hash(implode(',', array_filter([$path, $this->category(), $this->tag()])));
            $tags = kirby()->cache('bnomei.kart.tags')->getOrSet('tags-'.$key, fn () => $this->getTags($path), $expire);
        } else {
            $tags = $this->getTags($path);
        }

        return new Collection(array_map(fn ($c) => new Tag($c), $tags)); // @phpstan-ignore-line
    }

    public function tag(bool $multiple = false): ?string
    {
        $tags = $this->allTags();

        if ($multiple) {
            $t = explode(',', param('tags', ''));
        } else {
            $t = [param('tag', '')];
        }
        $t = array_map(fn ($tag) => trim(strip_tags(urldecode(strval($tag)))), $t);
        $t = array_filter($t, fn ($tag) => ! empty($tag) && in_array($tag, $tags));
        if (empty($t)) {
            return null;
        }

        return implode(',', $t);
    }

    public function allTags(): array
    {
        $products = kart()->page(ContentPageEnum::PRODUCTS);
        if (! $products) {
            return [];
        }

        $expire = kart()->option('expire');
        if (is_int($expire)) {
            $tags = kirby()->cache('bnomei.kart.tags')->getOrSet('tags', function () use ($products) {
                $tags = $products->children()->pluck('tags', ',', true);
                sort($tags);

                return $tags;
            }, $expire);
        } else {
            $tags = $products->children()->pluck('tags', ',', true);
            sort($tags);
        }

        return $tags;
    }

    private function getTags(?string $path = null): array
    {
        $products = kart()->page(ContentPageEnum::PRODUCTS);
        if (! $products) {
            return [];
        }

        // $categories = $this->allCategories();
        $tags = $this->allTags();
        $category = $this->category();
        $tag = $this->tag();

        return array_map(fn ($t) => [
            'id' => $t,
            'label' => t('tags.'.$t, $t),
            'title' => t('tags.'.$t, $t),
            'text' => t('tags.'.$t, $t),
            'count' => $category ?
                $products->children()->filterBy('tags', $t, ',')->filterBy('categories', 'in', [$category], ',')->count() :
                $products->children()->filterBy('tags', $t, ',')->count(),
            'value' => $t,
            'isActive' => $t === $tag,
            'url' => ($path ? url($path) : $products->url()).'?tag='.urlencode($t),
            'urlWithParams' => url(
                $path ?? $products->id(),
                ['params' => [
                    'category' => $category,
                    'tag' => $t === $tag ? null : $t,
                ]]
            ),
        ], $tags);
    }

    /**
     * @return Collection<Category>
     */
    public function categories(?string $path = null): Collection
    {
        $expire = kart()->option('expire');
        if (is_int($expire)) {
            $key = Kart::hash(implode(',', array_filter([$path, $this->category(), $this->tag()])));
            $categories = kirby()->cache('bnomei.kart.categories')->getOrSet('categories-'.$key, fn () => $this->getCategories($path), $expire);
        } else {
            $categories = $this->getCategories($path);
        }

        return new Collection(array_map(fn ($c) => new Category($c), $categories));  // @phpstan-ignore-line
    }

    public function category(bool $multiple = false): ?string
    {
        $categories = $this->allCategories();

        if ($multiple) {
            $c = explode(',', param('categories', ''));
        } else {
            $c = [param('category', '')];
        }
        $c = array_map(fn ($cat) => trim(strip_tags(urldecode(strval($cat)))), $c);
        $c = array_filter($c, fn ($cat) => ! empty($cat) && in_array($cat, $categories));
        if (empty($c)) {
            return null;
        }

        return implode(',', $c);
    }

    public function allCategories(): array
    {
        $products = kart()->page(ContentPageEnum::PRODUCTS);
        if (! $products) {
            return [];
        }

        $expire = kart()->option('expire');
        if (is_int($expire)) {
            $categories = kirby()->cache('bnomei.kart.categories')->getOrSet('categories', function () use ($products) {
                $categories = $products->children()->pluck('categories', ',', true);
                sort($categories);

                return $categories;
            }, $expire);
        } else {
            $categories = $products->children()->pluck('categories', ',', true);
            sort($categories);
        }

        return $categories;
    }

    private function getCategories(?string $path = null): array
    {
        $products = kart()->page(ContentPageEnum::PRODUCTS);
        if (! $products) {
            return [];
        }

        $categories = $this->allCategories();
        // $tags = $this->allTags();
        $category = $this->category();
        $tag = $this->tag();

        return array_map(fn ($c) => [
            'id' => $c,
            'label' => t('category.'.$c, $c),
            'title' => t('category.'.$c, $c),
            'text' => t('category.'.$c, $c),
            'value' => $c,
            'count' => $tag ?
                $products->children()->filterBy('categories', $c, ',')->filterBy('tags', 'in', [$tag], ',')->count() :
                $products->children()->filterBy('categories', $c, ',')->count(),
            'isActive' => $c === $category,
            'url' => ($path ? url($path) : $products->url()).'?category='.urlencode($c),
            'urlWithParams' => url(
                $path ?? $products->id(),
                ['params' => [
                    'category' => $c === $category ? null : $c,
                    'tag' => $tag,
                ]]
            ),
        ], $categories);
    }

    /**
     * @kql-allowed
     */
    public function productsWithTag(string|array $tags, bool $any = true): Pages
    {
        if (is_string($tags)) {
            $tags = [$tags];
        }
        sort($tags);
        $tags = array_unique(array_map(fn ($tag) => urldecode($tag), $tags));

        $expire = kart()->option('expire');
        if (is_int($expire)) {
            $key = 'tags-'.Kart::hash(implode(',', $tags)).($any ? '-any' : '-all');

            return new Pages(array_filter(kirby()->cache('bnomei.kart.products')->getOrSet($key, function () use ($tags, $any) {
                $products = $any ? $this->products()->filterBy('tags', 'in', $tags, ',') :
                    $this->products()->filterBy(fn ($product) => count(array_diff($tags, $product->tags()->split())) === 0);

                return array_values($products->toArray(fn (ProductPage $product) => $product->uuid()->toString()));
            }, $expire)), fn ($id) => $this->kirby()->page($id) !== null);
        }

        return $any ? $this->products()->filterBy('tags', 'in', $tags, ',') :
            $this->products()->filterBy(fn ($product) => count(array_diff($tags, $product->tags()->split())) === 0);
    }

    /**
     * @kql-allowed
     */
    public function stocks(): Pages
    {
        return kart()->page(ContentPageEnum::STOCKS)?->children() ?: new Pages;
    }

    public static function query(mixed $template = null, mixed $model = null): string
    {
        // array|Closure|string|null could be passed from I18n::translate
        if (! is_string($template)) {
            return '';
        }

        $page = null;
        $file = null;
        $site = kirby()->site();
        $user = kirby()->user();
        if ($model instanceof Page) {
            $page = $model;
        } elseif ($model instanceof File) {
            $file = $model;
        } elseif ($model instanceof Site) {
            $site = $model;
        } elseif ($model instanceof User) {
            $user = $model;
        }

        return Str::template($template, [
            'kirby' => kirby(),
            'site' => $site,
            'page' => $page,
            'file' => $file,
            'user' => $user,
            'model' => $model,
        ]);
    }

    protected ?array $kerbs = null;

    public function toKerbs(): array
    {
        if ($this->kerbs) {
            return $this->kerbs;
        }

        return $this->kerbs = array_filter([
            'cart' => $this->cart()->toKerbs(),
            'options' => [
                'turnstile' => [
                    'sitekey' => $this->option('turnstile.sitekey'),
                ],
            ],
            'urls' => $this->urls()->toKerbs(),
            'wishlist' => $this->wishlist()->toKerbs(),
        ]);
    }
}
