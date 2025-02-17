<?php

namespace Bnomei\Kart;

use Bnomei\Kart\Provider\Kirby;
use Bnomei\Kart\Provider\Mollie;
use Bnomei\Kart\Provider\Paddle;
use Bnomei\Kart\Provider\Stripe;
use Exception;
use Kirby\Cms\App;
use Kirby\Cms\Collection;
use Kirby\Cms\Page;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\Str;

class Kart
{
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

        $this->makeContentPages();
    }

    public function provider(): Provider
    {
        if (! $this->provider) {
            $this->provider = match ($this->kirby->option('bnomei.kart.provider')) {
                'kirby' => new Kirby($this->kirby),
                'mollie' => new Mollie($this->kirby),
                'paypal' => new Paddle($this->kirby),
                'stripe' => new Stripe($this->kirby),
            };
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

    public function checkout(): string
    {
        return Router::checkout();
    }

    public function login(): string
    {
        return Router::login();
    }

    public function logout(): string
    {
        return Router::logout();
    }

    public function lines(): Collection
    {
        return $this->cart->lines();
    }

    public function count(): int
    {
        return $this->cart->count();
    }

    public function quantity(): int
    {
        return $this->cart->quantity();
    }

    public function sum(): string
    {
        return Helper::formatCurrency($this->cart->sum());
    }

    public function tax(): string
    {
        return Helper::formatCurrency($this->cart->tax());
    }

    public function sumtax(): string
    {
        return Helper::formatCurrency($this->cart->sumtax());
    }

    public static function singleton(): Kart
    {
        if (self::$singleton === null) {
            self::$singleton = new self;
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
                $caches = array_keys(kirby()->option('bnomei.turbo.cache'));
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

    public function page(string $key): ?Page
    {
        $id = $this->kirby->option("bnomei.kart.{$key}.page");

        return $this->kirby->page($id);
    }

    public function makeContentPages(): void
    {
        $pages = array_filter([
            'orders' => $this->kirby->option('bnomei.kart.orders.enabled') === true ? \OrdersPage::class : null,
            'products' => \ProductsPage::class,
            'stocks' => $this->kirby->option('bnomei.kart.stocks.enabled') === true ? \StocksPage::class : null,
        ]);

        $this->kirby->impersonate('kirby', function () use ($pages) {
            foreach ($pages as $key => $class) {
                if (! $this->page($key)) {
                    $title = str_replace('Page', '', $class);
                    $page = site()->createChild([
                        'id' => $this->kirby->option("bnomei.kart.{$key}.page"),
                        'template' => Str::lower($title),
                        'content' => [
                            'title' => $title,
                            'uuid' => Str::lower($title),
                        ],
                    ]);
                    // force unlisted
                    Dir::move($page->root(), str_replace('_drafts/', '', $page->root()));
                }
            }
        });

    }

    public function currency(): string
    {
        return $this->kirby->option('bnomei.kart.currency');
    }
}
