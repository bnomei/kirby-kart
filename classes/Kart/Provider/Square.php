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

class Square extends Provider
{
    protected string $name = ProviderEnum::SQUARE->value;

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

        $lines = A::get($options, 'line_items', []);
        unset($options['line_items']);

        $orderOptions = A::get($options, 'order', []);
        unset($options['order']);

        $quickPay = A::get($options, 'quick_pay');
        unset($options['quick_pay']);

        if (is_array($quickPay) && ! array_key_exists('location_id', $quickPay)) {
            $quickPay['location_id'] = $this->option('location_id');
        }

        $orderLines = A::get($orderOptions, 'line_items', []);
        unset($orderOptions['line_items']);

        $lineItems = array_merge($lines, $orderLines, $this->kart->cart()->lines()->values(function (CartLine $l) use ($lineItem) {
            return array_merge([
                'metadata' => [
                    'product_uuid' => $l->product()?->uuid()->id(),
                ],
                'name' => $l->product()?->title()->value(),
                'quantity' => strval($l->quantity()),
                'item_type' => 'ITEM',
                'base_price_money' => [
                    'amount' => (int) round($l->price() * 100),
                    'currency' => $this->kart->currency(),
                ],
                // catalog_object_id
            ], $lineItem($this->kart, $l));
        }));

        $order = array_filter(array_merge([
            'location_id' => $this->option('location_id'),
            'customer_id' => $this->userData('customerId'),
            'line_items' => $lineItems,
        ], $orderOptions));

        $uuid = Str::uuid();

        // https://developer.squareup.com/reference/square/checkout-api/create-payment-link
        $remote = Remote::post('https://connect.squareup.com/v2/online-checkout/payment-links', [
            'headers' => array_filter([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('access_token')),
                'Square-Version' => $this->option('api_version'),
            ]),
            'data' => json_encode(array_filter(array_merge([
                'idempotency_key' => $uuid,
                // https://developer.squareup.com/reference/square/checkout-api/create-payment-link#request__property-checkout_options
                'checkout_options' => [
                    // 'ask_for_shipping_address' => true,
                    // 'enable_coupon' => true,
                    // 'enable_loyalty' => true,
                    // 'accepted_payment_methods' => [],
                    // https://developer.squareup.com/reference/square/objects/Checkout#definition__property-redirect_url
                    // ?checkoutId=xxxxxx&amp;orderId=xxxxxx&amp;referenceId=xxxxxx&amp;transactionId=xxxxxx
                    'redirect_url' => url(Router::PROVIDER_SUCCESS),
                ],
                $quickPay ? 'quick_pay' : 'order' => $quickPay ?: $order,
            ], $options))),
        ]);

        $json = in_array($remote->code(), [200, 201]) ? $remote->json() : null;
        if (! is_array($json)) {
            throw new \Exception('Checkout failed', $remote->code());
        }

        $session_id = A::get($json, 'payment_link.order_id');
        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.session_id', $session_id);

        // https://developer.squareup.com/reference/square/checkout-api/create-payment-link#response__property-payment_link
        return parent::checkout() && $remote->code() === 200 ?
            A::get($json, 'payment_link.long_url') : '/';
    }

    public function completed(array $data = []): array
    {
        // get session from current session id param
        $sessionId = get('orderId') ?? get('order_id');
        if (! $sessionId || ! is_string($sessionId) || $sessionId !== $this->kirby->session()->get('bnomei.kart.'.$this->name.'.session_id')) {
            return [];
        }

        // https://developer.squareup.com/reference/square/orders-api/retrieve-order
        $remote = Remote::get('https://connect.squareup.com/v2/orders/'.$sessionId, [
            'headers' => array_filter([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('access_token')),
                'Square-Version' => $this->option('api_version'),
            ]),
        ]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        if (! is_array($json)) {
            return [];
        }

        $customer = [];
        // https://developer.squareup.com/reference/square/customers-api/retrieve-customer
        $remote = Remote::get('https://connect.squareup.com/v2/customers/'.A::get($json, 'order.customer_id'), [
            'headers' => array_filter([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('access_token')),
                'Square-Version' => $this->option('api_version'),
            ]),
        ]);

        $cust = $remote->code() === 200 ? $remote->json() : null;
        if (is_array($cust)) {
            $customer = A::get($cust, 'customer');
        }

        $data = array_merge($data, array_filter([
            // 'session_id' => $sessionId,
            'email' => A::get($customer, 'email_address'),
            'customer' => [
                'id' => A::get($customer, 'id'),
                'email' => A::get($customer, 'email_address'),
                'name' => trim(A::get($customer, 'given_name').' '.A::get($customer, 'family_name')),
            ],
            'paidDate' => date('Y-m-d H:i:s', strtotime(A::get($json, 'order.updated_at'))),
            // 'paymentMethod' => implode(',', A::get($json, 'payment_method_types', [])),
            'paymentComplete' => A::get($json, 'order.state') === 'COMPLETED',
            // 'invoiceurl' => '',
            'paymentId' => A::get($json, 'order.id'),
        ]));

        $uuid = kart()->option('products.product.uuid');
        if ($uuid instanceof Closure === false) {
            return [];
        }

        /** @var \Closure $likey */
        $likey = kart()->option('licenses.license.uuid');

        foreach (A::get($json, 'order.line_items', []) as $line) {
            $data['items'][] = [
                // 'key' => ['page://'.$uuid(null, ['id' => A::get($price, 'product_id')])],  // pages field expect an array
                'key' => ['page://'.A::get($line, 'metadata.product_uuid')],  // pages field expect an array
                'variant' => null, // TODO: variant
                'quantity' => A::get($line, 'quantity'),
                'price' => round(A::get($line, 'base_price_money.amount', 0) / 100.0, 2),
                // these values include the multiplication with quantity
                'total' => round(A::get($line, 'total_money.amount', 0) / 100.0, 2), // TODO: validate if this is x quantity or not
                'subtotal' => round(A::get($line, 'gross_sales_money.amount', 0) / 100.0, 2),
                'tax' => round(A::get($line, 'total_tax_money.amount', 0) / 100.0, 2),
                'discount' => round(A::get($line, 'total_discount_money.amount', 0) / 100.0, 2),
                'licensekey' => A::get($line, 'uid', $likey($data + $json + ['line' => $line])),
            ];
        }

        $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.session_id');

        return parent::completed($data);
    }
}
