<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart\Mixins;

use Kirby\Toolkit\A;

trait Options
{
    private array $options;

    public function option(?string $key = null, mixed $default = null): mixed
    {
        if (! isset($this->options)) {
            $this->options = [];
        }

        if (! $key) {
            return $this->options;
        }

        $notSet = '%%UNDEFINED%%';
        $option = A::get($this->options, $key, $notSet);
        if ($option === $notSet) {
            $option = kirby()->option('bnomei.kart.'.$key, $default);
            $this->options[$key] = $option;
        }
        if (! is_string($option) && is_callable($option) && ! in_array($key, [
            'captcha.current',
            'captcha.get',
            'captcha.set',
            'orders.order.uuid',
            'products.product.uuid',
            'stocks.stock.uuid',
            'providers.fastspring.checkout_options',
            'providers.invoice_ninja.checkout_options',
            'providers.kirby_cms.checkout_options',
            'providers.lemonsqueeze.checkout_options',
            'providers.mollie.checkout_options',
            'providers.paddle.checkout_options',
            'providers.payone.checkout_options',
            'providers.paypal.checkout_options',
            'providers.snipcart.checkout_options',
            'providers.stripe.checkout_options',
        ])) {
            $option = $option();
            $this->options[$key] = $option;
        }

        return $option;
    }

    public function setOption(string $key, mixed $value): void
    {
        $this->options[$key] = $value;
    }
}
