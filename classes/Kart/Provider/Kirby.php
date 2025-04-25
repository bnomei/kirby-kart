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

        $input = Kart::sanitize(array_filter([
            'email' => strtolower(urldecode(strval(get('email', $this->kirby->user()?->email())))),
            'name' => urldecode(strval(get('name', $this->kirby->user()?->name()))),
            'payment_method' => urldecode(strval(get('payment_method', ''))),
            'payment_status' => urldecode(strval(get('payment_status', ''))),
            'invoiceurl' => urldecode(strval(get('invoiceurl', ''))),
        ]));

        // build data for user, order and stock updates
        $data = array_merge($data, array_filter([
            'email' => A::get($input, 'email'),
            'customer' => [
                'email' => A::get($input, 'email'),
                'name' => A::get($input, 'name'),
            ],
            'paidDate' => date('Y-m-d H:i:s'),
            'paymentMethod' => A::get($input, 'payment_method'),
            'paymentComplete' => A::get($input, 'payment_status', 'paid') === 'paid',
            'invoiceurl' => A::get($input, 'invoiceurl'), // unknown at this point
            'items' => kart()->cart()->lines()->values(fn (CartLine $l) => [
                'key' => [$l->product()?->uuid()->toString()], // pages field expect an array
                'variant' => $l->variant(),
                'quantity' => $l->quantity(),
                'price' => $l->product()?->price()->toFloat(), // per item
                'total' => $l->quantity() * $l->product()?->price()->toFloat(), // -discount +tax
                'subtotal' => $l->quantity() * $l->product()?->price()->toFloat(),
                'tax' => 0,
                'discount' => 0,
            ]),
        ]));

        $this->kirby->session()->remove('kart.'.$this->name.'.session_id');

        return parent::completed($data);
    }
}
