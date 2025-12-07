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
            // dynamic
            'captcha.current',
            'captcha.get',
            'captcha.set',
            'checkoutFormData',
            'completed',
            'licenses.activate',
            'licenses.deactivate',
            'licenses.license.uuid',
            'licenses.validate',
            'orders.order.uuid',
            'orders.order.zip',
            'products.product.uuid',
            'stocks.stock.uuid',
            // mutators
            'providers.chargebee.checkout_line',
            'providers.chargebee.checkout_options',
            'providers.fastspring.checkout_line',
            'providers.fastspring.checkout_options',
            'providers.gumroad.checkout_line',
            'providers.gumroad.checkout_options',
            'providers.invoice_ninja.checkout_line',
            'providers.invoice_ninja.checkout_options',
            'providers.kirby_cms.checkout_line',
            'providers.kirby_cms.checkout_options',
            'providers.lemonsqueezy.checkout_line',
            'providers.lemonsqueezy.checkout_options',
            'providers.mollie.checkout_line',
            'providers.mollie.checkout_options',
            'providers.paddle.checkout_line',
            'providers.paddle.checkout_options',
            'providers.paypal.checkout_line',
            'providers.paypal.checkout_options',
            'providers.polar.checkout_line',
            'providers.polar.checkout_options',
            'providers.shopify.checkout_line',
            'providers.shopify.checkout_options',
            'providers.snipcart.checkout_line',
            'providers.snipcart.checkout_options',
            'providers.square.checkout_line',
            'providers.square.checkout_options',
            'providers.stripe.checkout_line',
            'providers.stripe.checkout_options',
            'providers.sumup.checkout_line',
            'providers.sumup.checkout_options',
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
