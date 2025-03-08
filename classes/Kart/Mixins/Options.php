<?php

namespace Bnomei\Kart\Mixins;

use Kirby\Toolkit\A;

trait Options
{
    private array $options;

    public function option(?string $key = null): mixed
    {
        if (! $key) {
            return $this->options;
        }

        $option = A::get($this->options, $key);
        if (! $option) {
            $option = kart()->option(''.$key);
            $this->options[$key] = $option;
        }
        if (! is_string($option) && is_callable($option) && ! in_array($key, [
            'captcha.current',
            'captcha.get',
            'captcha.set',
            'orders.order.uuid',
            'products.product.uuid',
            'stocks.stock.uuid',
            'provider.stripe.checkout_options',
        ])) {
            $option = $option();
            $this->options[$key] = $option;
        }

        return $option;
    }
}
