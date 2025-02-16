<?php

namespace Bnomei\Kart;

use Closure;
use Kirby\Cms\App;

use function env;

abstract class Provider implements ProviderInterface
{
    protected string $name;

    private App $kirby;

    public function __construct()
    {
        $this->kirby = kirby();
    }

    protected function env(string $env, mixed $default = null): mixed
    {
        return env($env, $default);
    }

    public function option($key, bool $resolveCallables = true): mixed
    {
        $option = $this->kirby->option("bnomei.kart.{$this->name}.$key");
        if ($resolveCallables && $option instanceof Closure) {
            return $option();
        }

        return $option;
    }
}
