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
use Kirby\Toolkit\Str;

class Checkout extends Provider
{
    protected string $name = ProviderEnum::CHECKOUT->value;

    // API docs:
    // - Hosted Payments Page guide: https://www.checkout.com/docs/payments/accept-payments/accept-a-payment-on-a-hosted-page/manage-your-hosted-payments-page
    // - API reference (hosted payments session): https://api-reference.checkout.com/#operation/createAHostedPaymentsSession

    private function endpoint(string $path): string
    {
        return rtrim(strval($this->option('endpoint')), '/').$path;
    }

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

        $lineItem = $this->option('checkout_line', false);
        if ($lineItem instanceof Closure === false) {
            $lineItem = fn ($kart, $item) => [];
        }

        $contact = $this->checkoutContact();
        $billing = $this->checkoutBillingAddress();
        $shipping = $this->checkoutShippingAddress();

        $billingAddress = ! empty($billing) ? array_filter([
            'address_line1' => $billing['address1'] ?? null,
            'address_line2' => $billing['address2'] ?? null,
            'city' => $billing['city'] ?? null,
            'zip' => $billing['postal_code'] ?? null,
            'country' => isset($billing['country']) ? strtoupper($billing['country']) : null,
        ], fn ($value) => $value !== null && $value !== '') : [];

        $shippingAddress = ! empty($shipping) ? array_filter([
            'address_line1' => $shipping['address1'] ?? null,
            'address_line2' => $shipping['address2'] ?? null,
            'city' => $shipping['city'] ?? null,
            'zip' => $shipping['postal_code'] ?? null,
            'country' => isset($shipping['country']) ? strtoupper($shipping['country']) : null,
        ], fn ($value) => $value !== null && $value !== '') : [];

        $products = A::get($options, 'products', []);
        unset($options['products']);

        $sessionId = Str::uuid();
        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.cart_hash', $this->kart->cart()->hash());

        $uuid = kart()->option('orders.order.uuid');
        if ($uuid instanceof Closure) {
            $uuid = $uuid();
        }

        $payload = array_filter(array_merge([
            'amount' => intval(round($this->kart->cart()->subtotal() * 100)), // minor units
            'currency' => $this->kart->currency(),
            'reference' => strtoupper(strval($uuid)),
            'success_url' => url(Router::PROVIDER_SUCCESS),
            'failure_url' => url(Router::PROVIDER_CANCEL),
            'cancel_url' => url(Router::PROVIDER_CANCEL),
            'customer' => array_filter([
                'email' => $contact['email'] ?? $this->kirby->user()?->email(),
                'name' => $contact['name'] ?? $this->kirby->user()?->name()->value(),
            ]),
            'billing' => empty($billingAddress) ? null : [
                'address' => $billingAddress,
            ],
            'shipping' => empty($shippingAddress) ? null : $shippingAddress,
            'products' => array_merge($products, $this->kart->cart()->lines()->values(fn (CartLine $l) => array_merge([
                'name' => $l->product()?->title()->value(),
                'quantity' => $l->quantity(),
                'reference' => $l->product()?->uuid()->id().($l->variant() ? '|'.$l->variant() : ''),
                'unit_price' => intval(round($l->price() * 100)), // minor units
            ], $lineItem($this->kart, $l)))),
        ], $options), fn ($v) => $v !== null && $v !== [] && $v !== '');

        $remote = Remote::post($this->endpoint('/hosted-payments'), [
            'headers' => $this->headers(),
            'data' => json_encode($payload),
        ]);

        $json = in_array($remote->code(), [200, 201]) ? $remote->json() : null;
        if (! is_array($json)) {
            throw new \Exception('Checkout failed', $remote->code());
        }

        $id = A::get($json, 'id', $sessionId);
        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.session_id', $id);

        return parent::checkout() && $remote->code() < 300 ?
            A::get($json, '_links.redirect.href', '/') : '/';
    }

    public function completed(array $data = []): array
    {
        $sessionId = strval(get('cko-session-id', get('cko-payment-id', get('session_id'))));
        if ($sessionId === '' || $sessionId !== $this->kirby->session()->get('bnomei.kart.'.$this->name.'.session_id')) {
            return [];
        }

        if ($this->kirby->session()->get('bnomei.kart.'.$this->name.'.cart_hash') !== $this->kart->cart()->hash()) {
            return [];
        }

        $remote = Remote::get($this->endpoint('/hosted-payments/'.$sessionId), [
            'headers' => $this->headers(),
        ]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        if (! is_array($json)) {
            return [];
        }

        $paymentId = A::get($json, 'payment_id');
        $payment = [];
        if ($paymentId) {
            $remote = Remote::get($this->endpoint('/payments/'.$paymentId), [
                'headers' => $this->headers(),
            ]);
            $payment = $remote->code() === 200 ? $remote->json() : [];
        }

        $payment = is_array($payment) ? $payment : [];

        $paidDateRaw = A::get($payment, 'processed_on', A::get($payment, 'approved_on'));
        $paidDateTimestamp = $paidDateRaw ? strtotime(strval($paidDateRaw)) : false;
        $paidDate = $paidDateTimestamp !== false ?
            date('Y-m-d H:i:s', $paidDateTimestamp) :
            date('Y-m-d H:i:s');

        /** @var \Closure $likey */
        $likey = kart()->option('licenses.license.uuid');

        $data = array_merge($data, array_filter([
            'email' => A::get($payment, 'customer.email', A::get($json, 'customer.email')),
            'customer' => array_filter([
                'id' => A::get($payment, 'customer.id'),
                'email' => A::get($payment, 'customer.email', A::get($json, 'customer.email')),
                'name' => A::get($payment, 'customer.name', A::get($json, 'customer.name')),
            ]),
            'paidDate' => $paidDate,
            'paymentMethod' => A::get($payment, 'source.type', 'checkout_com'),
            'paymentComplete' => strtolower(strval(A::get($json, 'status'))) === 'payment received',
            'invoiceurl' => null,
            'paymentId' => $paymentId ?: $sessionId,
            'items' => kart()->cart()->lines()->values(fn (CartLine $l) => [
                'key' => [$l->product()?->uuid()->toString()], // pages field expect an array
                'variant' => $l->variant(),
                'quantity' => $l->quantity(),
                'price' => $l->price(), // per item
                'total' => $l->subtotal(),
                'subtotal' => $l->subtotal(),
                'tax' => 0,
                'discount' => 0,
                'licensekey' => $likey(['line' => $l->toArray()]),
            ]),
        ], fn ($v) => $v !== null && $v !== [] && $v !== ''));

        $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.cart_hash');
        $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.session_id');

        return parent::completed($data);
    }
}
