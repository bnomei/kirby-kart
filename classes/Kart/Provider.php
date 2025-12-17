<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Closure;
use Kirby\Cache\Cache;
use Kirby\Cms\App;
use Kirby\Cms\User;
use Kirby\Content\Field;
use Kirby\Data\Yaml;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Date;
use Kirby\Toolkit\Str;

abstract class Provider
{
    protected string $name;

    protected Kart $kart;

    private array $options;

    private array $cache;

    public function __construct(protected App $kirby)
    {
        $this->kart = $this->kirby->site()->kart();
        $this->cache = [];
        $this->options = [];
    }

    public function title(): string
    {
        return implode(' ', array_map('ucfirst', (explode('_', $this->name))));
    }

    public function virtual(): bool|array
    {
        $virtual = $this->kirby()->option("bnomei.kart.providers.{$this->name}.virtual", true);
        if (is_array($virtual) || is_bool($virtual)) {
            return $virtual;
        }

        return false;
    }

    public function option(string $key, bool $resolveCallables = true): mixed
    {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }

        $option = $this->kart->option("providers.{$this->name}.$key");
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

    public function userData(string $key): mixed
    {
        return A::get($this->getUserData(), $key);
    }

    public function getUserData(?User $user = null): array
    {
        $user ??= $this->kirby->user();

        if (! $user || $user->isCustomer() === false) {
            return [];
        }

        $field = $this->name; // no prefix to align with KLUB

        return $user->$field()->isNotEmpty() ? Yaml::decode($user->$field()->value()) : [];
    }

    public function setUserData(array $data, ?User $user): ?User
    {
        $user ??= $this->kirby->user();
        $data = array_filter($data);

        if (empty($data) || ! $user || $user->isCustomer() === false) {
            return $user;
        }

        $data = array_merge($this->getUserData(), $data);

        kirby()->impersonate('kirby', function () use ($user, $data) {
            $field = $this->name; // no prefix to align with KLUB

            return $user->update([
                $field => Yaml::encode($data),
            ]);
        });

        // fetch most current model
        return kirby()->user($user->email());
    }

    public function sync(ContentPageEnum|string|null $sync = null): int
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

    public function updatedAt(ContentPageEnum|string|null $sync = null): string
    {
        $u = 'updatedAt';
        if ($sync instanceof ContentPageEnum) {
            $u .= '-'.$sync->value;
        } elseif (is_string($sync)) {
            $u .= '-'.$sync;
        }
        $u = $this->cache()->get($u);

        return $u ? (new Field(null, 'updatedAt', $u))->toDate(kart()->dateFormat()) : '?';
    }

    public function findImagesFromUrls(string|array $urls): array
    {
        if (empty($urls)) {
            return [];
        }

        if (is_string($urls)) {
            $urls = Str::contains($urls, ',') ? explode(',', $urls) : [$urls];
        }

        $urls = array_map(fn ($i) => trim($i), $urls);

        // media pool in the products page
        $images = $this->kirby()->site()->kart()->page(ContentPageEnum::PRODUCTS)->images();

        return array_filter(array_map(
            // fn ($url) => $this->kart()->option('products.page').'/'.F::filename($url), // simple but does not resolve change in extension
            fn ($url) => Str::startsWith($url, 'file://') ? $url : $images->filter('name', F::name($url))->first()?->uuid()->toString(), // slower but better results
            $urls
        ));
    }

    public function name(): string
    {
        return $this->name;
    }

    protected function moneyValue(float|int $amount): string
    {
        return number_format(max(0, $amount), 2, '.', '');
    }

    public function findFilesFromUrls(string|array $urls): array
    {
        if (empty($urls)) {
            return [];
        }

        if (is_string($urls)) {
            $urls = Str::contains($urls, ',') ? explode(',', $urls) : [$urls];
        }

        $urls = array_map(fn ($i) => trim($i), $urls);

        // media pool in the products page
        $images = $this->kirby()->site()->kart()->page(ContentPageEnum::PRODUCTS)->files();

        return array_filter(array_map(
            // fn ($url) => $this->kart()->option('products.page').'/'.F::filename($url), // simple but does not resolve change in extension
            fn ($url) => Str::startsWith($url, 'file://') ? $url : $images->filter('name', F::name($url))->first()?->uuid()->toString(), // slower but better results
            $urls
        ));
    }

    public function portal(?string $returnUrl = null): ?string
    {
        return null;
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

        $expire = $this->kart->option('expire');
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
            $this->kart->option('successPage') // if null will use order page after creation
        );

        $this->kirby()->session()->set(
            'kart.redirect.canceled',
            Router::get('redirect')
        );

        // put stock into hold
        kart()->cart()->holdStock();

        $this->kirby()->trigger('kart.provider.'.$this->name.'.checkout');

        if (! $this->kirby()->environment()->isLocal() && $this->kirby()->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
            return null;
        }

        return '/';
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }

    public function handleWebhook(array $payload, array $headers = []): WebhookResult
    {
        return WebhookResult::ignored('Webhooks not implemented for '.$this->name);
    }

    protected function webhookCacheKey(string $eventId): string
    {
        return 'webhook.'.$eventId;
    }

    protected function isDuplicateWebhook(string $eventId): bool
    {
        return $this->cache()->get($this->webhookCacheKey($eventId)) !== null;
    }

    protected function rememberWebhook(string $eventId, int $days = 7): void
    {
        $this->cache()->set($this->webhookCacheKey($eventId), true, $days * 24 * 60);
    }

    public function canceled(): string
    {
        kirby()->trigger('kart.provider.'.$this->name.'.canceled');
        kart()->cart()->releaseStock();

        return $this->kirby()->session()->pull('kart.redirect.canceled', $this->kirby()->site()->url());
    }

    public function completed(array $data = []): array
    {
        $checkout = kirby()->session()->get('bnomei.kart.checkout_form_data', []);
        $completed = kart()->option('completed');
        if ($completed instanceof Closure) {
            $data = $completed($data, $checkout) ?? $data;
        }

        kirby()->trigger('kart.provider.'.$this->name.'.completed', ['data' => $data]);

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
