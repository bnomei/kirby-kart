<?php

namespace Bnomei\Kart;

use Bnomei\Kart\Mixins\ContentPages;
use Bnomei\Kart\Provider\Kirby;
use Exception;
use Kirby\Cms\App;
use Kirby\Cms\Collection;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\User;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use Kirby\Toolkit\V;
use OrderPage;
use ProductPage;
use StockPage;

class Kart
{
    use ContentPages;

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

    public function kirby(): App
    {
        return $this->kirby;
    }

    public function page(ContentPageEnum|string $key): ?Page
    {
        if ($key instanceof ContentPageEnum) {
            $key = $key->value;
            $key = strval($this->kirby()->option("bnomei.kart.{$key}.page"));
        }

        return $this->kirby()->page($key);
    }

    public function ready(): void
    {
        $this->makeContentPages();

        if (sha1(file_get_contents(__DIR__.strrev(base64_decode('cGhwLmVzbmVjaUwv')))) !== 'c6187eac0a6659724beb632dcb46806ee24a7e81' && $kart = base64_decode('c2xlZXA=')) { // @phpstan-ignore-line
            $kart(5); // @phpstan-ignore-line
        }
    }

    public function message(?string $message = null, string $channel = 'default'): ?string
    {
        if ($message === null) {
            return $this->kirby()->session()->pull('bnomei.kart.message-'.$channel);
        }

        $this->kirby()->session()->set('bnomei.kart.message-'.$channel, $message);

        return null;
    }

    public static function singleton(): Kart
    {
        if (self::$singleton === null) {
            self::$singleton = new self;
            self::$singleton->ready();
        }

        return self::$singleton;
    }

    public static function flush(string $cache = 'all'): bool
    {
        if (kirby()->option('bnomei.kart.expire') === null) {
            return false;
        }

        try {
            $caches = [];
            if (empty($cache) || $cache === '*' || $cache === 'all') {
                $caches = array_keys((array) kirby()->option('bnomei.kart.cache'));
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

    public function provider(): Provider
    {
        if (! $this->provider) {
            $class = strval($this->kirby()->option('bnomei.kart.provider'));
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

    public function cart(): Cart
    {
        if (! $this->cart) {
            $this->cart = new Cart('cart');
        }

        return $this->cart;
    }

    public function wishlist(): Cart
    {
        if (! $this->wishlist) {
            $this->wishlist = new Cart('wishlist');
        }

        return $this->wishlist;
    }

    public function currency(): string
    {
        return strval($this->kirby()->option('bnomei.kart.currency'));
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

    public function createCustomer(array $credentials): ?User
    {
        $email = A::get($credentials, 'email');
        $customer = $this->kirby()->users()->findBy('email', $email);
        if (! $customer && V::email($email) && $this->kirby()->option('bnomei.kart.customers.enabled')) {
            $customer = $this->kirby()->impersonate('kirby', function () use ($credentials, $email) {
                return $this->kirby()->users()->create([
                    'email' => $email,
                    'name' => A::get($credentials, 'name', ''),
                    'password' => Str::random(16),
                    'role' => ((array) $this->kirby()->option('bnomei.kart.customers.roles'))[0],
                ]);
            });
            $this->kirby()->trigger('kart.user.created', ['user' => $customer]);
        }

        return $customer;
    }

    /**
     * @return Collection<string, Category>
     */
    public function categories(?string $path = null): Collection
    {
        $products = kart()->page(ContentPageEnum::PRODUCTS);
        $categories = $products->children()->pluck('categories', ',', true);
        sort($categories);

        $category = param('category');
        $tag = param('tag');

        return new Collection(array_map(fn ($c) => new Category([
            'id' => $c,
            'label' => t('category.'.$c, $c),
            'title' => t('category.'.$c, $c),
            'text' => t('category.'.$c, $c),
            'value' => $c,
            'count' => $products->children()->filterBy('categories', $c, ',')->count(),
            'isActive' => $c === $category,
            'url' => ($path ? url($path) : $products->url()).'?category='.$c,
            'urlWithParams' => url(
                $path ?? $products->id(),
                ['params' => [
                    'category' => $c === $category ? null : $c,
                    'tag' => $tag,
                ]]
            ),
        ]), $categories));
    }

    /**
     * @return Collection<string, Tag>
     */
    public function tags(?string $path = null): Collection
    {
        $products = kart()->page(ContentPageEnum::PRODUCTS);
        $tags = $products->children()->pluck('tags', ',', true);
        sort($tags);

        $category = param('category');
        $tag = param('tag');

        return new Collection(array_map(fn ($t) => new Tag([
            'id' => $t,
            'label' => t('category.'.$t, $t),
            'title' => t('category.'.$t, $t),
            'text' => t('category.'.$t, $t),
            'count' => $products->children()->filterBy('tags', $t, ',')->count(),
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
        ]), $tags));
    }

    /**
     * @kql-allowed
     *
     * @return Pages<string, OrderPage>
     */
    public function orders(): Pages
    {
        return kart()->page(ContentPageEnum::ORDERS)?->children() ?: new Pages;
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
    public function productsByParams(): Pages
    {
        $products = $this->products();

        if ($category = param('category')) {
            $products = $products->filterBy('categories', $category, ',');
        }
        if ($categories = param('categories')) {
            $products = $products->filterBy('categories', 'in', explode(',', $categories), ',');
        }
        if ($tag = param('tag')) {
            $products = $products->filterBy('tags', $tag, ',');
        }
        if ($tags = param('tags')) {
            $products = $products->filterBy('tags', 'in', explode(',', $tags), ',');
        }

        return $products;
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
    public function productsWithCategory(string|array $category, bool $any = true): Pages
    {
        if (is_string($category)) {
            $category = [$category];
        }

        return $any ? $this->products()->filterBy('categories', 'in', $category, ',') :
            $this->products()->filterBy(fn ($product) => count(array_diff($category, $product->categories()->split())) === 0);
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

        return $any ? $this->products()->filterBy('tags', 'in', $tags, ',') :
            $this->products()->filterBy(fn ($product) => count(array_diff($tags, $product->tags()->split())) === 0);
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
