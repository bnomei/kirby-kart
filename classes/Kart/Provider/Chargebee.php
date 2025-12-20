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

class Chargebee extends Provider
{
    protected string $name = ProviderEnum::CHARGEBEE->value;

    private function buildAddress(array $address, array $contact = []): array
    {
        if (empty($address)) {
            return [];
        }

        return array_filter([
            'first_name' => $address['first_name'] ?? $contact['first_name'] ?? null,
            'last_name' => $address['last_name'] ?? $contact['last_name'] ?? null,
            'company' => $address['company'] ?? null,
            'line1' => $address['address1'] ?? null,
            'line2' => $address['address2'] ?? null,
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? null,
            'zip' => $address['postal_code'] ?? null,
            'country' => isset($address['country']) ? strtoupper($address['country']) : null,
        ], fn ($value) => $value !== null && $value !== '');
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
        $billingAddress = $this->buildAddress($this->checkoutBillingAddress(), $contact);
        $shippingAddress = $this->buildAddress($this->checkoutShippingAddress(), $contact);

        $lines = A::get($options, 'item_prices', []);
        unset($options['item_prices']);

        // https://apidocs.chargebee.com/docs/api/hosted_pages#checkout_new_for_items
        $payload = array_filter(array_merge([
            'redirect_url' => url(Router::PROVIDER_SUCCESS),
            'cancel_url' => url(Router::PROVIDER_CANCEL),
            'customer' => array_filter([
                'email' => $contact['email'] ?? null,
                'first_name' => $contact['first_name'] ?? null,
                'last_name' => $contact['last_name'] ?? null,
            ]),
            'billing_address' => $billingAddress ?: null,
            'shipping_address' => $shippingAddress ?: null,
            'item_prices' => array_merge($lines, $this->kart->cart()->lines()->values(function (CartLine $line) use ($lineItem) {
                $raw = $line->product()?->raw()->yaml() ?? [];
                $priceId = A::get($raw, 'id');

                // fall back to first active non-recurring price if present
                if (! $priceId) {
                    foreach (A::get($raw, 'prices', []) as $price) {
                        if (A::get($price, 'status') === 'active') {
                            $priceId = A::get($price, 'id');
                            break;
                        }
                    }
                }

                return array_merge([
                    'item_price_id' => $priceId,
                    'quantity' => $line->quantity(),
                ], $lineItem($this->kart, $line));
            })),
        ], $options));

        $remote = Remote::post($this->endpoint().'/hosted_pages/checkout_new_for_items', [
            'headers' => $this->headers(true),
            'data' => json_encode($payload),
        ]);

        $json = in_array($remote->code(), [200, 201]) ? $remote->json() : null;
        if (! is_array($json)) {
            throw new \Exception('Checkout failed', $remote->code());
        }

        $sessionId = A::get($json, 'hosted_page.id');
        if ($sessionId) {
            $this->kirby->session()->set('bnomei.kart.'.$this->name.'.session_id', $sessionId);
        }

        return parent::checkout() && $remote->code() < 300 ?
            A::get($json, 'hosted_page.url', '/') : '/';
    }

    public function completed(array $data = []): array
    {
        // Chargebee appends `id` and `state` to the redirect URL. Only continue when it matches our session.
        $sessionId = get('id');
        $state = get('state');
        if (! $sessionId || ! is_string($sessionId) ||
            $sessionId !== $this->kirby->session()->get('bnomei.kart.'.$this->name.'.session_id') ||
            $state !== 'succeeded') {
            return [];
        }

        // https://apidocs.eu.chargebee.com/docs/api/hosted_pages#retrieve_a_hosted_page
        $remote = Remote::get($this->endpoint().'/hosted_pages/'.$sessionId, [
            'headers' => $this->headers(),
        ]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        if (! is_array($json)) {
            return [];
        }

        $hosted = A::get($json, 'hosted_page', $json);
        if (! is_array($hosted) || A::get($hosted, 'state') !== 'succeeded') {
            return [];
        }

        $content = A::get($hosted, 'content', []);
        $invoice = A::get($content, 'invoice', []);
        $customer = A::get($content, 'customer', []);

        $formatAmount = function (int|string|null $value): float {
            if (is_string($value)) {
                return str_contains($value, '.') ? round(floatval($value), 2) : round(intval($value) / 100.0, 2);
            }

            return round(intval($value) / 100.0, 2);
        };

        $paymentMethod = array_filter([
            A::get($content, 'card.card_type'),
            ($last4 = A::get($content, 'card.last4')) ? '••••'.$last4 : null,
        ]);

        $paidAt = A::get($invoice, 'paid_at', A::get($hosted, 'updated_at', time()));
        $data = array_merge($data, array_filter([
            'email' => A::get($customer, 'email'),
            'customer' => [
                'id' => A::get($customer, 'id'),
                'email' => A::get($customer, 'email'),
                'name' => trim(A::get($customer, 'first_name').' '.A::get($customer, 'last_name')),
            ],
            'paidDate' => date('Y-m-d H:i:s', is_numeric($paidAt) ? intval($paidAt) : time()),
            'paymentMethod' => empty($paymentMethod) ? null : implode(' ', $paymentMethod),
            'paymentComplete' => A::get($invoice, 'status') === 'paid',
            'invoiceurl' => A::get($invoice, 'id'),
            'paymentId' => A::get($invoice, 'id', A::get($hosted, 'id')),
        ]));

        $uuid = kart()->option('products.product.uuid');
        if ($uuid instanceof Closure === false) {
            return [];
        }

        /** @var \Closure $likey */
        $likey = kart()->option('licenses.license.uuid');

        foreach (A::get($invoice, 'line_items', []) as $line) {
            $itemPriceId = A::get($line, 'item_price_id', A::get($line, 'entity_id'));
            if (! $itemPriceId) {
                continue;
            }
            $lineAmount = $formatAmount(A::get($line, 'amount', A::get($line, 'amount_in_decimal')));
            $taxAmount = $formatAmount(A::get($line, 'tax_amount', 0));
            $discountAmount = $formatAmount(A::get($line, 'discount_amount', 0));

            $data['items'][] = [
                'key' => ['page://'.$uuid(null, ['id' => $itemPriceId])],  // pages field expect an array
                'variant' => A::get($line, 'description', ''),
                'quantity' => intval(A::get($line, 'quantity', 1)),
                'price' => $formatAmount(A::get($line, 'unit_amount', A::get($line, 'unit_amount_in_decimal'))),
                // these values include the multiplication with quantity
                'total' => $lineAmount,
                'subtotal' => max(0, $lineAmount - $taxAmount),
                'tax' => $taxAmount,
                'discount' => $discountAmount,
                'licensekey' => $likey($data + $hosted + ['line' => $line, 'invoice' => $invoice]),
            ];
        }

        $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.session_id');

        return parent::completed($data);
    }

    public function fetchProducts(): array
    {
        $products = [];
        $offset = null;

        while (true) {
            // https://apidocs.chargebee.com/docs/api/item_prices#list_item_prices
            $remote = Remote::get($this->endpoint().'/item_prices', [
                'headers' => $this->headers(),
                'data' => array_filter([
                    'item_type[is]' => 'non_recurring',
                    'status[is]' => 'active',
                    'limit' => 100,
                    'offset' => $offset,
                ]),
            ]);

            $json = $remote->code() === 200 ? $remote->json() : null;
            if (! is_array($json)) {
                break;
            }

            foreach (A::get($json, 'list', []) as $entry) {
                $itemPrice = A::get($entry, 'item_price', []);
                if (! is_array($itemPrice)) {
                    continue;
                }

                $products[A::get($itemPrice, 'id')] = $itemPrice;
            }

            $offset = A::get($json, 'next_offset');
            if (! $offset) {
                break;
            }
        }

        return array_map(fn (array $data) => (new VirtualPage(
            $data,
            [
                'id' => 'id',
                'title' => fn ($i) => A::get($i, 'name', A::get($i, 'item_id', A::get($i, 'id'))),
                'content' => [
                    'description' => 'description',
                    'price' => fn ($i) => round(A::get($i, 'price', 0) / 100.0, 2),
                    'tags' => fn ($i) => A::get($i, 'metadata.tags', ''),
                    'categories' => fn ($i) => A::get($i, 'metadata.categories', ''),
                    'gallery' => fn ($i) => $this->findImagesFromUrls(
                        A::get($i, 'metadata.gallery', [])
                    ),
                    'downloads' => fn ($i) => $this->findFilesFromUrls(
                        A::get($i, 'metadata.downloads', [])
                    ),
                ],
            ],
            $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), $products);
    }

    public function portal(?string $returnUrl = null): ?string
    {
        $customer = $this->userData('customerId');
        if (! $customer) {
            return null;
        }

        // https://apidocs.chargebee.com/docs/api/portal_sessions#create_a_portal_session_for_a_customer
        $remote = Remote::post($this->endpoint().'/portal_sessions', [
            'headers' => $this->headers(true),
            'data' => json_encode(array_filter([
                'customer' => [
                    'id' => $customer,
                ],
                'redirect_url' => $returnUrl,
            ])),
        ]);

        $json = in_array($remote->code(), [200, 201]) ? $remote->json() : null;
        if (! is_array($json)) {
            return null;
        }

        return A::get($json, 'portal_session.access_url');
    }

    private function endpoint(): string
    {
        $site = strval($this->option('site'));

        return 'https://'.$site.'.chargebee.com/api/v2';
    }

    private function headers(bool $json = false): array
    {
        $headers = [
            'Authorization' => 'Basic '.base64_encode(strval($this->option('api_key')).':'),
            'Accept' => 'application/json',
        ];

        if ($json) {
            $headers['Content-Type'] = 'application/json';
        }

        if ($version = $this->option('api_version')) {
            $headers['Chargebee-Api-Version'] = $version;
        }

        return $headers;
    }
}
