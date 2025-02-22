<?php

namespace Bnomei\Kart;

use Bnomei\Kart\Mixins\CartShortcuts;
use Bnomei\Kart\Mixins\ContentPages;
use Bnomei\Kart\Provider\Kirby;
use Exception;
use Kirby\Cms\App;
use Kirby\Cms\Page;

class Kart
{
    use CartShortcuts;
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

    public function page(ContentPageEnum|string $key): ?Page
    {
        if ($key instanceof ContentPageEnum) {
            $key = $key->value;
            $key = $this->kirby->option("bnomei.kart.{$key}.page");
        }

        return $this->kirby->page($key);
    }

    public function ready(): void
    {
        $this->makeContentPages();
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
                $caches = array_keys(kirby()->option('bnomei.kart.cache'));
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
            $class = $this->kirby->option('bnomei.kart.provider');
            if (class_exists($class)) {
                $this->provider = new $class($this->kirby);
            } else {
                $this->provider = new Kirby($this->kirby);
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
        return $this->kirby->option('bnomei.kart.currency');
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
}
