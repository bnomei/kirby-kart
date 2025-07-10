<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\CartLine;
use Bnomei\Kart\Kart;
use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;
use Bnomei\Kart\Router;
use Closure;
use Exception;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

class Sumup extends Provider
{
    protected string $name = ProviderEnum::SUMUP->value;

    private function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.strval($this->option('secret_key')),
        ];
    }

    public function checkout(): string
    {
        $options = $this->option('checkout_options', false);
        if ($options instanceof Closure) {
            $options = $options($this->kart);
        }

        $session_id = Str::uuid();

        // https://developer.sumup.com/api/checkouts/create
        $remote = Remote::post('https://api.sumup.com/v0.1/checkouts', [
            'headers' => $this->headers(),
            'data' => json_encode(array_filter(array_merge([
                'checkout_reference' => $session_id,
                'amount' => $this->kart->cart()->subtotal(),
                'currency' => $this->kart->currency(),
                // 'customer_id' => null, // intended only for subscriptions
                // 'pay_to_email' => null // deprecated
                'merchant_code' => $this->option('merchant_code'),
                'return_url' => url(Router::PROVIDER_SUCCESS).'?session_id='.$session_id,
            ], $options))),
        ]);

        $json = in_array($remote->code(), [200, 201]) ? $remote->json() : null;
        if (! is_array($json)) {
            throw new Exception('Checkout failed', $remote->code());
        }

        $checkout_id = A::get($remote->json(), 'id');

        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.cart_hash', $this->kart->cart()->hash());
        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.customer', array_merge(
            kirby()->session()->get('bnomei.kart.'.$this->name.'.customer', []),
            array_filter([
                'email' => Router::get('email'),
                'name' => Router::get('name'),
            ])
        ));
        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.checkout_id', $checkout_id);
        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.session_id', $session_id);

        return parent::checkout() ? Router::provider_payment([
            'session_id' => $session_id,
        ]) : '/';
    }

    public function completed(array $data = []): array
    {
        // get session from current session id param
        $sessionId = get('session_id');
        if (! $sessionId || ! is_string($sessionId) || $sessionId !== $this->kirby->session()->get('bnomei.kart.'.$this->name.'.session_id')) {
            return [];
        }

        $checkoutId = $this->kirby->session()->get('bnomei.kart.'.$this->name.'.checkout_id');
        $customer = (array) Kart::sanitize($this->kirby->session()->get('bnomei.kart.'.$this->name.'.customer', []));

        // https://developer.sumup.com/api/checkouts/get
        $remote = Remote::get('https://api.sumup.com/v0.1/checkouts/'.$checkoutId, [
            'headers' => $this->headers(),
        ]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        if (! is_array($json)) {
            return [];
        }

        // check that cart has not been modified
        if ($this->kirby->session()->get('bnomei.kart.'.$this->name.'.cart_hash') !== $this->kart->cart()->hash()) {
            return [];
        }

        /** @var \Closure $likey */
        $likey = kart()->option('licenses.license.uuid');

        $data = array_merge($data, array_filter([
            // 'session_id' => $sessionId,
            'email' => A::get($customer, 'email'),
            'customer' => [
                'id' => A::get($customer, 'id'),
                'email' => A::get($customer, 'email'),
                'name' => A::get($customer, 'name'),
            ],
            'paidDate' => date('Y-m-d H:i:s', strtotime(A::get($json, 'date'))),
            // 'paymentMethod' => implode(',', A::get($json, 'payment_method_types', [])),
            'paymentComplete' => A::get($json, 'status') === 'PAID',
            'invoiceurl' => null,
            'paymentId' => A::get($json, 'id'),
            'items' => kart()->cart()->lines()->values(fn (CartLine $l) => [
                'key' => [$l->product()?->uuid()->toString()], // pages field expect an array
                'variant' => $l->variant(),
                'quantity' => $l->quantity(),
                'price' => $l->price(), // per item
                'total' => $l->quantity() * $l->price(), // -discount +tax
                'subtotal' => $l->quantity() * $l->price(),
                'tax' => 0,
                'discount' => 0,
                'licensekey' => $likey(['line' => $l->toArray()]),
            ]),
        ]));

        $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.cart_hash');
        $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.checkout_id');
        $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.customer');
        $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.session_id');

        return parent::completed($data);
    }
}
