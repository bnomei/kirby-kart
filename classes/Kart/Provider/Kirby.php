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
use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Kart;
use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;
use Bnomei\Kart\Router;
use Kirby\Toolkit\A;
use Kirby\Uuid\Uuid;

class Kirby extends Provider
{
    protected string $name = ProviderEnum::KIRBY->value;

    public function updatedAt(ContentPageEnum|string|null $sync = null): string
    {
        return strval(t('bnomei.kart.now'));
    }

    public function checkout(): string
    {
        $session_id = sha1(Uuid::generate());
        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.session_id', $session_id);
        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.cart_hash', $this->kart->cart()->hash());

        // TEMPLATE FOR BUILDING PROVIDERS
        /*
        $options = $this->option('checkout_options', false);
        if ($options instanceof \Closure) {
            $options = $options($this->kart);
        }

        $lineItem = $this->option('checkout_line', false);
        if ($lineItem instanceof Closure === false) {
            $lineItem = fn ($kart, $item) => [];
        }

        $lines = A::get($options, 'lines', []);
        unset($options['lines']);

        $data = array_filter(array_merge([
            'success_url' => url(Router::PROVIDER_SUCCESS).'?session_id='.$session_id,
            'lines' => array_merge($lines, $this->kart->cart()->lines()->values(fn (CartLine $l) => array_merge([
                'sku' => $l->product()?->uuid()->id().($l->variant() ? '|'.$l->variant() : ''), // used on completed again to find the product
                'type' => $l->product()?->ptype()->isNotEmpty() ?
                    $l->product()?->ptype()->value() : 'physical',
                'description' => $l->product()?->title()->value(),
                'quantity' => $l->quantity(),
                'unitPrice' => [
                    'currency' => $this->kart->currency(),
                    'value' => number_format($l->price(), 2),
                ],
                'totalAmount' => [
                    'currency' => $this->kart->currency(),
                    'value' => number_format($l->subtotal(), 2),
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
            ], $lineItem($this->kart, $l)))),
        ], $options));
        */

        // kirby_cms provider does not call external API with $data but simply redirects to the PROVIDER_PAYMENT.
        // the $data will be constructed from the cart after PROVIDER_SUCCESS and used to create the order.
        return parent::checkout() ? Router::provider_payment([
            'success_url' => url(Router::PROVIDER_SUCCESS).'?session_id='.$session_id,
        ]) : '/';
    }

    public function completed(array $data = []): array
    {
        $sessionId = A::get($data, 'session_id', get('session_id'));
        if (! $sessionId || $sessionId !== $this->kirby->session()->get('bnomei.kart.'.$this->name.'.session_id')) {
            return [];
        }

        // check that cart has not been modified
        if ($this->kirby->session()->get('bnomei.kart.'.$this->name.'.cart_hash') !== $this->kart->cart()->hash()) {
            return [];
        }

        $input = (array) Kart::sanitize(array_filter([
            'email' => strtolower(urldecode(strval(get('email', $this->kirby->user()?->email())))),
            'name' => urldecode(strval(get('name', $this->kirby->user()?->name()))),
            'payment_method' => urldecode(strval(get('payment_method', ''))),
            'payment_status' => urldecode(strval(get('payment_status', ''))),
            'invoiceurl' => urldecode(strval(get('invoiceurl', ''))),
        ]));

        /** @var \Closure $likey */
        $likey = kart()->option('licenses.license.uuid');

        // build data for user, order and stock updates
        $data = array_merge($data, array_filter([
            'email' => A::get($input, 'email'),
            'customer' => [
                'email' => A::get($input, 'email'),
                'name' => A::get($input, 'name'),
            ],
            'paidDate' => date('Y-m-d H:i:s'),
            'paymentMethod' => strval(A::get($input, 'payment_method')),
            'paymentComplete' => A::get($input, 'payment_status', 'paid') === 'paid',
            'invoiceurl' => A::get($input, 'invoiceurl'), // unknown at this point
            'items' => kart()->cart()->lines()->values(fn (CartLine $l) => [
                'key' => [$l->product()?->uuid()->toString()], // pages field expect an array
                'variant' => $l->variant(),
                'quantity' => $l->quantity(),
                'price' => $l->price(), // per item
                'total' => $l->subtotal(), // -discount +tax
                'subtotal' => $l->subtotal(),
                'tax' => 0,
                'discount' => 0,
                'licensekey' => $likey($input + ['line' => $l->toArray()]),
            ]),
        ]));

        $this->kirby->session()->remove('kart.'.$this->name.'.session_id');

        return parent::completed($data);
    }
}
