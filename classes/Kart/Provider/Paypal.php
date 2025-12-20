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
use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;
use Bnomei\Kart\Router;
use Bnomei\Kart\VirtualPage;
use Closure;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

class Paypal extends Provider
{
    protected string $name = ProviderEnum::PAYPAL->value;

    protected ?string $token = null;

    private function token(): ?string
    {
        if ($this->token) {
            return $this->token;
        }

        // https://developer.paypal.com/api/rest/authentication/
        $endpoint = strval($this->option('endpoint'));
        $remote = Remote::post($endpoint.'/v1/oauth2/token?grant_type=client_credentials', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic '.base64_encode(strval($this->option('client_id')).':'.strval($this->option('client_secret'))),
            ],
        ]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        if (is_array($json)) {
            $this->token = A::get($json, 'access_token');
        }

        return $this->token;
    }

    private function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Prefer' => 'return=representation',
            'Authorization' => 'Bearer '.$this->token(),
        ];
    }

    public function checkout(): string
    {
        $options = $this->option('checkout_options', false);
        if ($options instanceof Closure) {
            $options = $options($this->kart);
        }

        $endpoint = strval($this->option('endpoint'));
        $currency = $this->kart->option('currency');

        $uuid = kart()->option('orders.order.uuid');
        if ($uuid instanceof Closure) {
            $uuid = $uuid();
        }

        $lineItem = $this->option('checkout_line', false);
        if ($lineItem instanceof Closure === false) {
            $lineItem = fn ($kart, $item) => [];
        }

        $contact = $this->checkoutContact();
        $name = $this->checkoutNameParts();
        $shippingAddress = $this->checkoutShippingAddress();
        $shippingRate = $this->checkoutShippingRate();

        $lines = A::get($options, 'items', []);
        unset($options['items']);

        // allow checkout_options to pass optional breakdown adjustments
        $cartSubtotal = $this->kart->cart()->subtotal();
        $taxTotal = floatval($options['tax_total'] ?? 0);
        unset($options['tax_total']);
        $shippingProvided = array_key_exists('shipping', $options);
        $shipping = floatval($options['shipping'] ?? 0);
        unset($options['shipping']);
        $handling = floatval($options['handling'] ?? 0);
        unset($options['handling']);
        $insurance = floatval($options['insurance'] ?? 0);
        unset($options['insurance']);
        $shippingDiscount = floatval($options['shipping_discount'] ?? 0);
        unset($options['shipping_discount']);
        $discount = floatval($options['discount'] ?? 0);
        unset($options['discount']);

        if (! $shippingProvided && $shippingRate !== null) {
            $shipping = $shippingRate;
        }

        $money = fn (float $amount) => [
            'currency_code' => $currency,
            'value' => $this->moneyValue($amount),
        ];

        $breakdown = [
            'item_total' => $money($cartSubtotal),
            'tax_total' => $money($taxTotal),
        ];

        if ($shipping > 0) {
            $breakdown['shipping'] = $money($shipping);
        }
        if ($handling > 0) {
            $breakdown['handling'] = $money($handling);
        }
        if ($insurance > 0) {
            $breakdown['insurance'] = $money($insurance);
        }
        if ($shippingDiscount > 0) {
            $breakdown['shipping_discount'] = $money($shippingDiscount);
        }
        if ($discount > 0) {
            $breakdown['discount'] = $money($discount);
        }

        $amountValue = $cartSubtotal + $taxTotal + $shipping + $handling + $insurance - $shippingDiscount - $discount;

        $payer = array_filter([
            'email_address' => $contact['email'] ?? null,
            'name' => array_filter([
                'given_name' => $name['first'] ?? null,
                'surname' => $name['last'] ?? null,
            ]),
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');

        $shippingPayload = [];
        if (! empty($shippingAddress)) {
            $shippingName = $shippingAddress['name'] ?? $contact['name'] ?? null;
            $shippingPayload = array_filter([
                'name' => $shippingName ? ['full_name' => $shippingName] : null,
                'address' => array_filter([
                    'address_line_1' => $shippingAddress['address1'] ?? null,
                    'address_line_2' => $shippingAddress['address2'] ?? null,
                    'admin_area_2' => $shippingAddress['city'] ?? null,
                    'admin_area_1' => $shippingAddress['state'] ?? null,
                    'postal_code' => $shippingAddress['postal_code'] ?? null,
                    'country_code' => isset($shippingAddress['country']) ? strtoupper($shippingAddress['country']) : null,
                ], fn ($value) => $value !== null && $value !== ''),
            ], fn ($value) => $value !== null && $value !== []);
        }

        // https://developer.paypal.com/docs/api/orders/v2/#orders_create
        $remote = Remote::post($endpoint.'/v2/checkout/orders', [
            'headers' => $this->headers(),
            'data' => json_encode(array_filter(array_merge([
                'intent' => 'CAPTURE',
                'payer' => $payer,
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                            'landing_page' => 'LOGIN',
                            'shipping_preference' => empty($shippingPayload) ? 'GET_FROM_FILE' : 'SET_PROVIDED_ADDRESS',
                            'user_action' => 'PAY_NOW',
                            'return_url' => url(Router::PROVIDER_SUCCESS),
                            'cancel_url' => url(Router::PROVIDER_CANCEL),
                        ],
                    ],
                ],
                'purchase_units' => [
                    [
                        'custom_id' => strtoupper($uuid),
                        // 'invoice_id' => strtoupper($uuid), // TODO: get invnum for next? locking?
                        'amount' => [
                            'currency_code' => $currency,
                            'value' => $this->moneyValue($amountValue),
                            // https://developer.paypal.com/docs/api/orders/v2/#orders_create!ct=application/json&path=purchase_units/amount/breakdown&t=request
                            'breakdown' => $breakdown,
                        ],
                        'shipping' => empty($shippingPayload) ? null : $shippingPayload,
                        'items' => array_merge($lines, $this->kart->cart()->lines()->values(fn (CartLine $l) => array_merge([
                            'sku' => $l->product()?->uuid()->id(), // used on completed again to find the product
                            'name' => $l->product()?->title()->value(),
                            'description' => $l->product()?->description()->value(),
                            // 'type' => A::get($l->product()?->raw()->yaml(), 'type'),
                            // 'category' => A::get($l->product()?->raw()->yaml(), 'category'),
                            'unit_amount' => [
                                'currency_code' => $currency,
                                'value' => $this->moneyValue($l->price()),
                            ],
                            'image_url' => A::get($l->product()?->raw()->yaml(), 'image_url', $l->product()?->firstGalleryImageUrl()),
                            'url' => $l->product()?->url(),
                            'quantity' => strval($l->quantity()),
                        ], $lineItem($this->kart, $l)))),
                    ],
                ],
            ], $options))),
        ]);

        $json = in_array($remote->code(), [200, 201]) ? $remote->json() : null;
        if (! is_array($json)) {
            throw new \Exception('Checkout failed', $remote->code());
        }

        $this->kirby->session()->set('kart.paypal.order.id', A::get($json, 'id'));
        $this->kirby->session()->set('kart.paypal.cart.hash', $this->kart->cart()->hash());

        $approveUrl = null;
        foreach (A::get($json, 'links', []) as $link) {
            if (! is_array($link)) {
                continue;
            }
            $rel = strtolower(strval(A::get($link, 'rel', '')));
            if (in_array($rel, ['approve', 'payer-action', 'payer_action'], true)) {
                $approveUrl = A::get($link, 'href');
                break;
            }
        }

        // https://www.sandbox.paypal.com/checkoutnow?token=...
        return parent::checkout() && $approveUrl && $remote->code() < 300 ?
            $approveUrl : '/';
    }

    public function completed(array $data = []): array
    {
        // get session from current session id
        $sessionId = $this->kirby->session()->get('kart.paypal.order.id');
        if (! is_string($sessionId)) {
            return [];
        }

        $endpoint = strval($this->option('endpoint'));

        // https://developer.paypal.com/docs/api/orders/v2/#orders_capture
        $remote = Remote::post($endpoint.'/v2/checkout/orders/'.$sessionId.'/capture', [
            'headers' => $this->headers(),
        ]);

        $json = in_array($remote->code(), [200, 201]) ? $remote->json() : null;
        if (! is_array($json)) {
            return [];
        }

        $capture = A::get($json, 'purchase_units.0.payments.captures.0', []);
        $captureStatus = strtoupper(strval(A::get($capture, 'status', '')));
        $orderStatus = strtoupper(strval(A::get($json, 'status', '')));

        // only proceed when payment has actually been captured
        if (! in_array($orderStatus, ['COMPLETED'], true) || ! in_array($captureStatus, ['COMPLETED'], true)) {
            return [];
        }

        $paymentMethod = A::get($json, 'payment_source', []);
        if (is_array($paymentMethod)) {
            $paymentMethod = implode(',', array_keys($paymentMethod));
        }

        $paidAt = A::get($capture, 'update_time', A::get($capture, 'create_time', A::get($json, 'update_time', A::get($json, 'create_time'))));
        $paidTimestamp = $paidAt ? strtotime(strval($paidAt)) : false;

        $payerName = trim(strval(A::get($json, 'payer.name.given_name')).' '.strval(A::get($json, 'payer.name.surname')));
        if ($payerName === '') {
            $payerName = trim(strval(A::get($json, 'payer.given_name')).' '.strval(A::get($json, 'payer.surname')));
        }

        $data = array_merge($data, array_filter([
            // 'session_id' => $sessionId,
            // 'uuid' => A::get($json, 'purchase_units.0.custom_id'),
            'email' => A::get($json, 'payer.email_address'),
            'customer' => [
                'id' => A::get($json, 'payer.payer_id'),
                'email' => A::get($json, 'payer.email_address'),
                'name' => $payerName,
            ],
            'paidDate' => $paidTimestamp !== false ? date('Y-m-d H:i:s', $paidTimestamp) : date('Y-m-d H:i:s'),
            'paymentMethod' => $paymentMethod,
            'paymentComplete' => true,
            // 'invoiceurl' => A::get($json, 'invoice'),
            'paymentId' => A::get($json, 'id'),
        ]));

        /** @var \Closure $likey */
        $likey = kart()->option('licenses.license.uuid');

        foreach (A::get($json, 'purchase_units.0.items') as $line) {
            $data['items'][] = [
                'key' => ['page://'.A::get($line, 'sku')],  // pages field expect an array
                'variant' => null, // TODO: variant
                'quantity' => intval(A::get($line, 'quantity')),
                'price' => round(floatval(A::get($line, 'unit_amount.value', 0)), 2),
                // these values include the multiplication with quantity
                'total' => round(floatval(A::get($line, 'unit_amount.value', 0)), 2) * intval(A::get($line, 'quantity')) + round(floatval(A::get($line, 'tax.value', 0)), 2),
                'subtotal' => round(floatval(A::get($line, 'unit_amount.value', 0)), 2) * intval(A::get($line, 'quantity')),
                'tax' => round(floatval(A::get($line, 'tax.value', 0)), 2),
                'discount' => 0, // NOTE: paypal has no discount per item
                'licensekey' => $likey($data + $json + ['line' => $line]),
            ];
        }
        // TODO: maybe add a line without a product linked if a global discount was set

        $this->kirby->session()->remove('kart.paypal.order.id');
        $this->kirby->session()->remove('kart.paypal.cart.hash');

        return parent::completed($data);
    }

    public function fetchProducts(): array
    {
        $products = [];
        $page = 1;
        $endpoint = strval($this->option('endpoint'));

        while (true) {
            // https://developer.paypal.com/docs/api/catalog-products/v1/#products_list
            $remote = Remote::get("$endpoint/v1/catalogs/products?page_size=20&page=$page&total_required=true", [
                'headers' => $this->headers(),
            ]);

            $json = $remote->code() === 200 ? $remote->json() : null;
            if (! is_array($json)) {
                break;
            }

            foreach (A::get($json, 'products') as $product) {
                $detailsUrl = null;
                foreach (A::get($product, 'links', []) as $link) {
                    if (! is_array($link)) {
                        continue;
                    }
                    if (strtolower(strval(A::get($link, 'rel', ''))) === 'self') {
                        $detailsUrl = A::get($link, 'href');
                        break;
                    }
                }
                if (! $detailsUrl && isset($product['id'])) {
                    $detailsUrl = $endpoint.'/v1/catalogs/products/'.$product['id'];
                }
                if (! $detailsUrl || ! is_string($detailsUrl)) {
                    continue;
                }

                $remote = Remote::get($detailsUrl, [
                    'headers' => $this->headers(),
                ]);

                $prod = $remote->code() === 200 ? $remote->json() : null;
                if (is_array($prod)) {
                    $products[$product['id']] = $prod;
                }
            }

            if ($page >= intval(A::get($json, 'total_pages'))) {
                break;
            }
            $page++;
        }

        return array_map(fn (array $data) => // NOTE: changes here require a cache flush to take effect
        (new VirtualPage(
            $data,
            [
                // MAP: kirby <=> paypal
                'id' => 'id', // id, uuid and slug will be hashed in ProductPage::create based on this `id`
                'title' => 'name',
                'content' => [
                    'created' => fn ($i) => date('Y-m-d H:i:s', strtotime($i['create_time'])),
                    'description' => 'description',
                    // tags
                    // category
                    // price
                    'gallery' => fn ($i) => $this->findImagesFromUrls(
                        A::get($i, 'image_url', [])
                    ),
                    // downloads
                ],
            ],
            $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), array_filter($products));
    }
}
