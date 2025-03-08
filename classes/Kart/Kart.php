<?php

namespace Bnomei\Kart;

use Bnomei\Kart\Mixins\Captcha;
use Bnomei\Kart\Mixins\ContentPages;
use Bnomei\Kart\Mixins\Options;
use Bnomei\Kart\Mixins\Turnstile;
use Bnomei\Kart\Provider\Kirby;
use Closure;
use Exception;
use Kirby\Cms\App;
use Kirby\Cms\Collection;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\User;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use Kirby\Toolkit\SymmetricCrypto;
use Kirby\Toolkit\V;
use Kirby\Uuid\Uuid;
use NumberFormatter;
use OrderPage;
use ProductPage;
use StockPage;

class Kart
{
    use Captcha;
    use ContentPages;
    use Options;
    use Turnstile;

    private static ?Kart $singleton = null;

    private ?Provider $provider;

    private ?Cart $cart;

    private ?Cart $wishlist;

    private App $kirby;

    public function __construct()
    {
        $this->kirby = kirby();
        $this->provider = null;
        $this->cart = null;
        $this->wishlist = null;
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

        if (sha1(file_get_contents(__DIR__.strrev(base64_decode('cGhwLmVzbmVjaUwv')))) !== 'c6187eac0a6659724beb632dcb46806ee24a7e81' && $kart = base64_decode('c2xlZXA=')) { // @phpstan-ignore-line
            $kart(5); // @phpstan-ignore-line
        }
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
        } catch (Exception $e) {
            // if given a cache that does not exist or is not flushable
            return false;
        }
    }

    public static function encrypt(mixed $data, ?string $password = null, bool $json = false): string
    {
        $password ??= option('crypto.password');
        if ($password instanceof Closure) {
            $password = $password();
        }
        if ($password && is_string($password) && SymmetricCrypto::isAvailable()) {
            if ($json || is_array($data)) {
                $data = json_encode($data) ?: '';
            }

            // encryption is slowish thus using a cache
            $expire = kart()->option('expire');
            if (is_int($expire)) {
                $key = Kart::hash($data);
                $data = kirby()->cache('bnomei.kart.crypto')->getOrSet($key, function () use ($data, $password) {
                    return is_string($data) ? (new SymmetricCrypto(password: $password))->encrypt($data) : $data;
                }, $expire);
            } else {
                $data = is_string($data) ? (new SymmetricCrypto(password: $password))->encrypt($data) : $data;
            }
        }

        return base64_encode(strval($data));
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

        $password ??= option('router.encryption');
        if ($password instanceof Closure) {
            $password = $password();
        }
        if ($password && SymmetricCrypto::isAvailable()) {
            if (Str::contains($data, '"mode":"secretbox"')) {
                $expire = kart()->option('expire');
                if (is_int($expire)) {
                    $key = Kart::hash($data);
                    $data = kirby()->cache('bnomei.kart.crypto')->getOrSet($key, function () use ($data, $password) {
                        return (new SymmetricCrypto(password: $password))->decrypt($data);
                    }, $expire);
                } else {
                    $data = (new SymmetricCrypto(password: $password))->decrypt($data);
                }
            }
            if ($json) {
                $data = json_decode($data, true);
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

    public function provider(): Provider
    {
        if (! $this->provider) {
            $class = strval($this->kart()->option('provider'));
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

    public function kirby(): App
    {
        return $this->kirby;
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
        return strval($this->kart()->option('currency'));
    }

    public function checkout(): string
    {
        return Router::cart_checkout();
    }

    public function login(): string
    {
        return Router::login();
    }

    public function logout(): string
    {
        return Router::logout();
    }

    public function sync(Page|string|null $page): string
    {
        return Router::sync($page);
    }

    public function canCheckout(): bool
    {
        if ($this->cart()->lines()->count() === 0) {
            return false;
        }

        /**
         * @var CartLine $line
         */
        foreach ($this->cart()->lines() as $line) {
            $stock = $line->product()?->stock();
            if (is_int($stock) && $stock < $line->quantity()) {
                kart()->message('bnomei.kart.out-of-stock', 'checkout');

                return false;
            }
        }

        return true;
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

    public function createCustomer(array $credentials): ?User
    {
        $email = A::get($credentials, 'email');
        $customer = $this->kirby()->users()->findBy('email', $email);
        if (! $customer && V::email($email) && $this->kart()->option('customers.enabled')) {
            $customer = $this->kirby()->impersonate('kirby', function () use ($credentials, $email) {
                return $this->kirby()->users()->create([
                    'email' => $email,
                    'name' => A::get($credentials, 'name', ''),
                    'password' => Str::random(16),
                    'role' => ((array) $this->kart()->option('customers.roles'))[0],
                ]);
            });
            $this->kirby()->trigger('kart.user.created', ['user' => $customer]);
        }

        return $customer;
    }

    /**
     * @kql-allowed
     *
     * @return Pages<string, OrderPage>
     */
    public function ordersWithProduct(ProductPage|string|null $product): Pages
    {
        return $this->orders()->filterBy(
            fn (OrderPage $orderPage) => $orderPage->hasProduct($product)
        );
    }

    /**
     * @kql-allowed
     *
     * @return Pages<string, OrderPage>
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
     *
     * @return Pages<string, OrderPage>
     */
    public function ordersWithCustomer(User|string|null $user): Pages
    {
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

    private function getProductsByParam(array $params = []): Pages
    {
        $products = $this->products();

        if ($category = A::get($params, 'category')) {
            $products = $products->filterBy('categories', $category, ',');
        }
        if ($categories = A::get($params, 'categories')) {
            $products = $products->filterBy('categories', 'in', explode(',', $categories), ',');
        }
        if ($tag = A::get($params, 'tag')) {
            $products = $products->filterBy('tags', $tag, ',');
        }
        if ($tags = A::get($params, 'tags')) {
            $products = $products->filterBy('tags', 'in', explode(',', $tags), ',');
        }

        return $products;
    }

    /**
     * @kql-allowed
     *
     * @return Pages<string, ProductPage>
     */
    public function productsByParams(array $params = []): Pages
    {
        $params = array_filter(array_merge($params, array_filter([
            'category' => param('category'),
            'categories' => param('categories'),
            'tag' => param('tag'),
            'tags' => param('tags'),
        ])));

        if (empty($params)) {
            return $this->products();
        }

        $expire = kart()->option('expire');
        if (is_int($expire)) {
            $key = Kart::hash(implode(',', array_filter($params)));
            $products = kirby()->cache('bnomei.kart.products')->getOrSet('products-'.$key, function () use ($params) {
                return array_values($this->getProductsByParam($params)->toArray(fn (ProductPage $product) => $product->uuid()->toString()));
            });

            return new Pages($products);
        }

        return $this->getProductsByParam($params);
    }

    /**
     * @kql-allowed
     *
     * @return Pages<string, ProductPage>
     */
    public function products(): Pages
    {
        return kart()->page(ContentPageEnum::PRODUCTS)?->children() ?: new Pages;
    }

    /**
     * @kql-allowed
     *
     * @return Pages<string, ProductPage>
     */
    public function productsWithoutStocks(): Pages
    {
        return $this->products()->filterBy(fn (ProductPage $page) => ! is_numeric($page->stock()));
    }

    /**
     * @kql-allowed
     *
     * @return Pages<string, ProductPage>
     */
    public function productsRelated(ProductPage $product): Pages
    {
        $withCategory = $this->productsWithCategory($product->categories());
        $withTag = $this->productsWithTag($product->tags());

        return $withCategory->merge($withTag);
    }

    /**
     * @kql-allowed
     *
     * @return Pages<string, ProductPage>
     */
    public function productsWithCategory(string|array $categories, bool $any = true): Pages
    {
        if (is_string($categories)) {
            $categories = [$categories];
        }
        sort($categories);
        $categories = array_unique($categories);

        $expire = kart()->option('expire');
        if (is_int($expire)) {
            $key = 'categories-'.Kart::hash(implode(',', $categories)).($any ? '-any' : '-all');

            return new Pages(kirby()->cache('bnomei.kart.products')->getOrSet($key, function () use ($categories, $any) {
                $products = $any ? $this->products()->filterBy('categories', 'in', $categories, ',') :
                    $this->products()->filterBy(fn ($product) => count(array_diff($categories, $product->tags()->split())) === 0);

                return array_values($products->toArray(fn (ProductPage $product) => $product->uuid()->toString()));
            }, $expire));
        }

        return $any ? $this->products()->filterBy('categories', 'in', $category, ',') :
            $this->products()->filterBy(fn ($product) => count(array_diff($category, $product->categories()->split())) === 0);
    }

    private function getCategories(?string $path = null): array
    {
        $products = kart()->page(ContentPageEnum::PRODUCTS);
        $categories = $products->children()->pluck('categories', ',', true);
        $tags = $products->children()->pluck('tags', ',', true);
        sort($categories);

        $category = param('category');
        $tag = param('tag');

        return array_map(fn ($c) => [
            'id' => $c,
            'label' => t('category.'.$c, $c),
            'title' => t('category.'.$c, $c),
            'text' => t('category.'.$c, $c),
            'value' => $c,
            'count' => $products->children()->filterBy('categories', $c, ',')->filterBy('tags', 'in', $tag ? [$tag] : $tags)->count(),
            'isActive' => $c === $category,
            'url' => ($path ? url($path) : $products->url()).'?category='.$c,
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
     * @return Collection<string, Category>
     */
    public function categories(?string $path = null): Collection
    {
        $expire = kart()->option('expire');
        if (is_int($expire)) {
            $key = Kart::hash(implode(',', array_filter([$path, param('category'), param('tag')])));
            $categories = kirby()->cache('bnomei.kart.categories')->getOrSet('categories-'.$key, function () use ($path) {
                return $this->getCategories($path);
            }, $expire);
        } else {
            $categories = $this->getCategories($path);
        }

        return new Collection(array_map(fn ($c) => new Category($c), $categories));
    }

    /**
     * @kql-allowed
     *
     * @return Pages<string, ProductPage>
     */
    public function productsWithTag(string|array $tags, bool $any = true): Pages
    {
        if (is_string($tags)) {
            $tags = [$tags];
        }
        sort($tags);
        $tags = array_unique($tags);

        $expire = kart()->option('expire');
        if (is_int($expire)) {
            $key = 'tags-'.Kart::hash(implode(',', $tags)).($any ? '-any' : '-all');

            return new Pages(kirby()->cache('bnomei.kart.products')->getOrSet($key, function () use ($tags, $any) {
                $products = $any ? $this->products()->filterBy('tags', 'in', $tags, ',') :
                    $this->products()->filterBy(fn ($product) => count(array_diff($tags, $product->tags()->split())) === 0);

                return array_values($products->toArray(fn (ProductPage $product) => $product->uuid()->toString()));
            }, $expire));
        }

        return $any ? $this->products()->filterBy('tags', 'in', $tags, ',') :
            $this->products()->filterBy(fn ($product) => count(array_diff($tags, $product->tags()->split())) === 0);
    }

    private function getTags(?string $path = null): array
    {
        $products = kart()->page(ContentPageEnum::PRODUCTS);
        $categories = $products->children()->pluck('categories', ',', true);
        $tags = $products->children()->pluck('tags', ',', true);
        sort($tags);

        $category = param('category');
        $tag = param('tag');

        return array_map(fn ($t) => [
            'id' => $t,
            'label' => t('category.'.$t, $t),
            'title' => t('category.'.$t, $t),
            'text' => t('category.'.$t, $t),
            'count' => $products->children()->filterBy('tags', $t, ',')->filterBy('categories', 'in', $category ? [$category] : $categories)->count(),
            'value' => $t,
            'isActive' => $t === $tag,
            'url' => ($path ? url($path) : $products->url()).'?tag='.$t,
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
     * @return Collection<string, Tag>
     */
    public function tags(?string $path = null): Collection
    {
        $expire = kart()->option('expire');
        if (is_int($expire)) {
            $key = Kart::hash(implode(',', array_filter([$path, param('category'), param('tag')])));
            $tags = kirby()->cache('bnomei.kart.tags')->getOrSet('tags-'.$key, function () use ($path) {
                return $this->getTags($path);
            }, $expire);
        } else {
            $tags = $this->getTags($path);
        }

        return new Collection(array_map(fn ($c) => new Tag($c), $tags));
    }

    /**
     * @kql-allowed
     *
     * @return Pages<string, StockPage>
     */
    public function stocks(): Pages
    {
        return kart()->page(ContentPageEnum::STOCKS)?->children() ?: new Pages;
    }
}
