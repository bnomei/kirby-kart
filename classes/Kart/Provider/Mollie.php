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
use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;
use Bnomei\Kart\Router;
use Closure;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

class Mollie extends Provider
{
    protected string $name = ProviderEnum::MOLLIE->value;

    public function checkout(): string
    {
        $options = $this->option('checkout_options', false);
        if ($options instanceof Closure) {
            $options = $options($this->kart);
        }

        $uuid = kart()->option('orders.order.uuid');
        if ($uuid instanceof Closure) {
            $uuid = $uuid();
        }

        $lineItem = $this->option('checkout_line', false);
        if ($lineItem instanceof Closure === false) {
            $lineItem = fn ($kart, $item) => [];
        }

        $customerId = $this->kirby()->user() ? $this->kart->provider()->userData('customerId') : null;
        if (! $customerId) {
            $email = get('email', $this->kirby()->user()?->email());
            $name = get('name', $this->kirby()->user()?->name()->value());

            // https://docs.mollie.com/reference/create-customer
            $remote = Remote::post('https://api.mollie.com/v2/customers', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Bearer '.strval($this->option('secret_key')),
                ],
                'data' => array_filter([
                    'email' => $email,
                    'name' => $name,
                ]),
            ]);

            if (in_array($remote->code(), [200, 201])) {
                $customerId = $remote->json()['id'];
            }
        }

        // https://docs.mollie.com/reference/create-payment
        $remote = Remote::post('https://api.mollie.com/v2/payments', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
            'data' => array_filter(array_merge([
                'description' => strval(t('bnomei.kart.order')).' '.strtoupper($uuid),
                'metadata' => [
                    'order_id' => $uuid,
                ],
                'locale' => $this->kirby->language()?->locale(),
                'method' => ['applepay', 'creditcard', 'paypal', 'twint'],
                'customerId' => $customerId,
                'redirectUrl' => url(Router::PROVIDER_SUCCESS).'?session_id='.urlencode($uuid),
                'cancelUrl' => url(Router::PROVIDER_CANCEL),
                'amount' => [
                    'currency' => $this->kart->currency(),
                    'value' => number_format($this->kart->cart()->subtotal(), 2),
                ],
                'billingAddress' => $this->kirby()->user() ? [
                    'email' => $this->kirby()->user()->email(),
                ] : null,
                'lines' => $this->kart->cart()->lines()->values(fn (CartLine $l) => array_merge([
                    'sku' => $l->product()?->uuid()->id(), // used on completed again to find the product
                    'type' => $l->product()?->ptype()->isNotEmpty() ? // @phpstan-ignore-line
                        $l->product()?->ptype()->value() : 'physical', // @phpstan-ignore-line
                    'description' => $l->product()?->title()->value(),
                    'quantity' => $l->quantity(),
                    'unitPrice' => [
                        'currency' => $this->kart->currency(),
                        'value' => number_format($l->product()?->price()->toFloat(), 2),
                    ],
                    'totalAmount' => [
                        'currency' => $this->kart->currency(),
                        'value' => number_format($l->product()?->price()->toFloat() * $l->quantity(), 2),
                    ],
                    'imageUrl' => $l->product()?->firstGalleryImageUrl(),
                    'productUrl' => $l->product()?->url(),
                    'vatRate' => 0, // use checkout_line to adjust
                    'vatAmount' => [
                        'currency' => $this->kart->currency(),
                        'value' => number_format(0, 2),
                    ], // use checkout_line to adjust
                    'discountAmount' => [
                        'currency' => $this->kart->currency(),
                        'value' => number_format(0, 2),
                    ], // use checkout_line to adjust
                ], $lineItem($this->kart, $l))),
            ], $options)),
        ]);

        $session_id = $remote->json()['id']; // tr_...
        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.session_id', $session_id);

        if (! in_array($remote->code(), [200, 201])) {
            throw new \Exception('Checkout failed', $remote->code());
        }

        return parent::checkout() && in_array($remote->code(), [200, 201]) ?
            $remote->json()['_links']['checkout']['href'] : '/';
    }

    public function completed(array $data = []): array
    {
        // get session from current PHP session
        $sessionId = $this->kirby->session()->get('bnomei.kart.'.$this->name.'.session_id');
        if (! $sessionId || ! is_string($sessionId)) {
            return [];
        }

        // https://docs.mollie.com/reference/get-payment
        $remote = Remote::get('https://api.mollie.com/v2/payments/'.$sessionId, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
        ]);
        if ($remote->code() !== 200) {
            return [];
        }

        $json = $remote->json();

        $customer = [];
        // this only works if the user has been linked on checkout creation
        if ($customerId = A::get($json, 'customerId')) {
            // https://docs.mollie.com/reference/get-customer
            $remote = Remote::get('https://api.mollie.com/v2/customers/'.$customerId, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.strval($this->option('secret_key')),
                ],
            ]);

            if ($remote->code() === 200) {
                $customer = $remote->json();
            }
        }

        $data = array_merge($data, array_filter([
            // 'session_id' => $sessionId,
            'uuid' => A::get($json, 'metadata.order_id'),
            'email' => A::get($customer, 'email'),
            'customer' => ! empty($customer) ? [
                'id' => A::get($customer, 'id'),
                'email' => A::get($customer, 'email'),
                'name' => A::get($customer, 'name'),
            ] : null,
            'paidDate' => date('Y-m-d H:i:s', strtotime(A::get($json, 'paidAt', A::get($json, 'createdAt')))),
            'paymentMethod' => implode(',', A::get($json, 'method', [])),
            'paymentComplete' => A::get($json, 'status') === 'paid',
            // 'invoiceurl' => A::get($json, 'invoice'),
            'paymentId' => A::get($json, 'id'),
        ]));

        /** @var \Closure $likey */
        $likey = kart()->option('licenses.license.uuid');

        foreach (A::get($json, 'lines') as $line) {
            $data['items'][] = [
                'key' => ['page://'.A::get($line, 'sku')],  // pages field expect an array
                'variant' => null, // TODO: variant
                'quantity' => A::get($line, 'quantity'),
                'price' => round(floatval(A::get($line, 'unitPrice.value', 0)), 2),
                // these values include the multiplication with quantity
                'total' => round(floatval(A::get($line, 'totalAmount.value', 0)), 2),
                'subtotal' => round(floatval(A::get($line, 'totalAmount.value', 0)), 2),
                'tax' => round(floatval(A::get($line, 'vatAmount.value', 0)), 2),
                'discount' => round(floatval(A::get($line, 'discountAmount.value', 0)), 2),
                'licensekey' => $likey($data + ['line' => $line]),
            ];
        }

        $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.session_id');

        return parent::completed($data);
    }
}
