<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Kirby\Toolkit\A;

class Licenses
{
    public function get(string $license_key): array
    {
        $licenses = kirby()->cache('bnomei.kart.licenses')->getOrSet('licenses', function () {
            $data = [];
            /** @var \OrderPage $order */
            foreach (kart()->orders() as $order) {
                /** @var OrderLine $line */
                foreach ($order->orderLines() as $line) {
                    $data[$line->licensekey()] = [$line->licensekey(), $order->uuid()->toString()];
                }
            }

            return $data;
        });

        [$license, $order] = A::get($licenses, $license_key, [null, null]);

        $error = null;
        if (is_null($license)) {
            $error = 'Invalid license key';
        }
        /** @var \OrderPage|null $order */
        $order = $order ? page($order) : null; // find order by stored uuid
        $customer = $order?->customer()->toUser();
        if (is_null($order)) {
            $error = 'Order for license key not found';
        }

        return [
            $license,
            $order,
            $customer,
            $error,
        ];
    }

    public function order(string $license_key): array
    {
        [$license, $order, $customer, $error] = $this->get($license_key);

        return $order;
    }

    public function customer(string $license_key): array
    {
        [$license, $order, $customer, $error] = $this->get($license_key);

        return $customer;
    }

    public function activate(string $license_key): array
    {
        [$license, $order, $customer, $error] = $this->get($license_key);

        $data = kart()->option('licenses.activate', []);
        if ($data instanceof \Closure) {
            $data = $data($license_key, $license, $order, $customer);
        }

        // similar to https://docs.lemonsqueezy.com/api/license-api/activate-license-key
        $data = array_merge([
            'activated' => is_string($license),
            'error' => is_string($error) ? $error : null,
            'license_key' => [
                'key' => $license,
                'created_at' => $order?->paidDate()->toDate('c'), // iso 8601
            ],
            'meta' => $order ? [
                'order_id' => $order->title()->value(),
                'customer_name' => $customer?->name()?->value(),
                'customer_email' => $customer?->email(),
            ] : [],
        ], $data);

        kirby()->trigger('kart.license.activate', ['data' => $data]);

        return $data;
    }

    public function deactivate(string $license_key): array
    {
        [$license, $order, $customer, $error] = $this->get($license_key);

        $data = kart()->option('licenses.deactivate', []);
        if ($data instanceof \Closure) {
            $data = $data($license_key, $license, $order, $customer);
        }

        // similar to https://docs.lemonsqueezy.com/api/license-api/deactivate-license-key
        $data = array_merge([
            'deactivated' => is_string($license),
            'error' => is_string($error) ? $error : null,
            'license_key' => [
                'key' => $license,
                'created_at' => $order?->paidDate()->toDate('c'), // iso 8601
            ],
            'meta' => $order ? [
                'order_id' => $order->title()->value(),
                'customer_name' => $customer?->name()?->value(),
                'customer_email' => $customer?->email(),
            ] : [],
        ], $data);

        kirby()->trigger('kart.license.deactivate', ['data' => $data]);

        return $data;
    }

    public function validate(string $license_key): array
    {
        [$license, $order, $customer, $error] = $this->get($license_key);

        $data = kart()->option('licenses.validate', []);
        if ($data instanceof \Closure) {
            $data = $data($license_key, $license, $order, $customer);
        }

        // similar to https://docs.lemonsqueezy.com/api/license-api/validate-license-key
        $data = array_merge([
            'con' => is_string($license),
            'error' => is_string($error) ? $error : null,
            'license_key' => [
                'key' => $license,
                'created_at' => $order?->paidDate()->toDate('c'), // iso 8601
            ],
            'meta' => $order ? [
                'order_id' => $order->title()->value(),
                'customer_name' => $customer?->name()?->value(),
                'customer_email' => $customer?->email(),
            ] : [],
        ], $data);

        kirby()->trigger('kart.license.validate', ['data' => $data]);

        return $data;
    }
}
