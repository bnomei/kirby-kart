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

        // https://docs.mollie.com/reference/create-payment
        $remote = Remote::post('https://api.mollie.com/v2/payments', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
            'data' => array_filter(array_merge([
                'description' => t('bnomei.kart.order').' '.strtoupper($uuid),
                'locale' => $this->kirby->language()?->locale(),
                'method' => ['applepay', 'creditcard', 'paypal', 'twint'],
                'customerId' => $this->kart->provider()->userData('customerId'),
                'redirectUrl' => url(Router::PROVIDER_SUCCESS).'?session_id='.urlencode($uuid),
                'cancelUrl' => url(Router::PROVIDER_CANCEL),
                'amount' => [
                    'currency' => $this->kart->currency(),
                    'value' => number_format($this->kart->cart()->subtotal(), 2),
                ],
                'billingAddress' => $this->kirby()->user() ? [
                    'email' => $this->kirby()->user()?->email(),
                ] : null,
                'lines' => $this->kart->cart()->lines()->values(fn (CartLine $l) => [
                    'sku' => $l->product()?->uuid()->id(),
                    'type' => $l->product()?->ptype()->isNotEmpty() ?
                        $l->product()?->ptype()->value() : 'physical',
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
                    'vatRate' => 0,
                    'vatAmount' => 0,
                ]),
            ], $options)),
        ]);

        $session_id = $remote->json()['id']; // tr_...
        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.session_id', $session_id);

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
        // TODO: this only works if the user has been linked on checkout creation
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
            'email' => A::get($customer, 'email'),
            'customer' => ! empty($customer) ? [
                'id' => A::get($customer, 'id'),
                'email' => A::get($customer, 'email'),
                'name' => A::get($customer, 'name'),
            ] : null,
            'paidDate' => date('Y-m-d H:i:s', strtotime(A::get($json, 'authorizedAt', A::get($json, 'createdAt')))),
            // 'paymentMethod' => implode(',', A::get($json, 'payment_method_types', [])),
            'paymentComplete' => A::get($json, 'status') === 'paid',
            // 'invoiceurl' => A::get($json, 'invoice'),
            'paymentId' => A::get($json, 'id'),
        ]));

        foreach (A::get($json, 'lines') as $line) {
            $data['items'][] = [
                'key' => ['page://'.A::get($line, 'sku')],  // pages field expect an array
                'quantity' => A::get($line, 'quantity'),
                'price' => round(floatval(A::get($line, 'unitPrice.value', 0)), 2),
                // these values include the multiplication with quantity
                'total' => round(floatval(A::get($line, 'totalAmount.value', 0)), 2),
                'subtotal' => round(floatval(A::get($line, 'totalAmount.value', 0)), 2),
                'tax' => 0, // TODO:
                'discount' => 0, // TODO
            ];
        }

        return parent::completed($data);
    }
}
