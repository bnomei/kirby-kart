<?php

namespace Bnomei\Kart;

use Closure;
use Kirby\Cache\Cache;
use Kirby\Cms\App;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Date;

abstract class Provider
{
    protected string $name;

    protected App $kirby;

    protected Kart $kart;

    private array $options;

    private array $cache;

    public function __construct(App $kirby)
    {
        $this->kirby = $kirby;
        $this->kart = $kirby->site()->kart();
        $this->cache = [];
        $this->options = [];
    }

    public function title(): string
    {
        return implode(' ', array_map('ucfirst', (explode('_', $this->name))));
    }

    public function virtual(): bool|string
    {
        return $this->kirby()->option("bnomei.kart.providers.{$this->name}.virtual", true);
    }

    public function option(string $key, bool $resolveCallables = true): mixed
    {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }

        $option = $this->kirby()->option("bnomei.kart.providers.{$this->name}.$key");
        if ($resolveCallables && $option instanceof Closure) {
            $option = $option();
        }
        $this->options[$key] = $option;

        return $option;
    }

    public function kirby(): App
    {
        return $this->kirby;
    }

    public function sync(ContentPageEnum|string|null $sync): int
    {
        $all = array_map(fn ($c) => $c->value, ContentPageEnum::cases());

        if (! $sync) {
            $sync = $all;
        }
        if ($sync instanceof ContentPageEnum) {
            $sync = [$sync->value];
        }
        if (is_string($sync)) {
            $sync = [$sync];
        }

        // only allow valid interfaces
        $sync = array_intersect($sync, $all);

        $t = microtime(true);

        foreach ($sync as $interface) {
            $this->cache[$interface] = null;
            $this->cache()->remove($interface);
            $this->$interface();
        }

        return intval(round(($t - microtime(true)) * 1000));
    }

    public function cache(): Cache
    {
        return $this->kirby()->cache('bnomei.kart.'.$this->name);
    }

    public function updatedAt(ContentPageEnum|string|null $sync): string
    {
        $u = 'updatedAt';
        if ($sync instanceof ContentPageEnum) {
            $u .= '-'.$sync->value;
        } elseif (is_string($sync)) {
            $u .= '-'.$sync;
        }

        return $this->cache()->get($u, '?');
    }

    public function findImagesFromUrls(array $urls): array
    {
        // media pool in the products page
        $images = $this->kirby()->site()->kart()->page(ContentPageEnum::PRODUCTS)->images();

        return array_filter(array_map(
            // fn ($url) => $this->kart()->option('products.page').'/'.F::filename($url), // simple but does not resolve change in extension
            fn ($url) => $images->filter('name', F::name($url))->first()?->uuid()->toString(), // slower but better results
            $urls
        ));
    }

    public function name(): string
    {
        return $this->name;
    }

    public function products(): array
    {
        return $this->read('products');
    }

    public function read(string $interface): array
    {
        // static per request cache
        if ($data = A::get($this->cache, $interface)) {
            return $data;
        }

        // file cache
        if ($data = $this->cache()->get($interface)) {
            return $data;
        }

        $method = 'fetch'.ucfirst($interface);
        $data = $this->$method(); // concrete implementation

        if (! $this->kirby()->environment()->isLocal() && $this->kirby()->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
            $data = array_slice($data, 0, 10);
        }

        $expire = $this->kart()->option('expire');
        if (! is_null($expire)) {
            $this->cache()->set($interface, $data, intval($expire));
        }

        // update timestamp
        $this->cache[$interface] = $data;
        $t = str_replace('+00:00', '', Date::now()->toString());
        $this->cache()->set('updatedAt', $t);
        $this->cache()->set('updatedAt-'.$interface, $t);

        return $data;
    }

    public function orders(): array
    {
        return $this->read('orders');
    }

    public function stocks(): array
    {
        return $this->read('stocks');
    }

    public function checkout(): ?string
    {
        $this->kirby()->session()->set(
            'kart.redirect.success',
            $this->kart()->option('successPage') // if null will use order page after creation
        );

        $this->kirby()->session()->set(
            'kart.redirect.canceled',
            Router::get('redirect')
        );

        if (! $this->kirby()->environment()->isLocal() && $this->kirby()->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
            return null;
        }

        return '/';
    }

    public function canceled(): string
    {
        kirby()->trigger('kart.'.$this->name.'.canceled');

        return $this->kirby()->session()->pull('kart.redirect.canceled', $this->kirby()->site()->url());
    }

    public function completed(array $data = []): array
    {
        kirby()->trigger('kart.'.$this->name.'.completed');

        return $data;
    }

    public function fetchProducts(): array
    {
        return [];
    }

    public function fetchOrders(): array
    {
        return [];
    }

    public function fetchStocks(): array
    {
        return [];
    }
}
