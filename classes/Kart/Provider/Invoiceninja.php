<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;
use Bnomei\Kart\VirtualPage;
use Bnomei\Kart\WebhookResult;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

class Invoiceninja extends Provider
{
    protected string $name = ProviderEnum::INVOICE_NINJA->value;

    public function checkout(): string
    {
        // create invoice + hosted payment link
        $clientId = strval($this->option('client_id', true) ?? '');
        if ($clientId === '') {
            return '/';
        }

        $lineItems = [];
        foreach ($this->kart->cart()->lines() as $line) {
            $price = floatval($line->price());
            $lineItems[] = array_filter([
                'product_key' => $line->product()?->id() ?? $line->id(),
                'notes' => $line->product()?->title()->value(),
                'cost' => $price,
                'quantity' => max(1, intval($line->quantity())),
            ], fn ($value) => $value !== null && $value !== '' && $value !== []);
        }

        if (empty($lineItems)) {
            return '/';
        }

        $remote = Remote::post($this->endpoint().'/invoices', [
            'headers' => $this->headers(json: true),
            'data' => json_encode(array_filter([
                'client_id' => $clientId,
                'line_items' => $lineItems,
                'status_id' => 1, // draft sent for payment
            ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        $json = in_array($remote->code(), [200, 201]) ? $remote->json() : null;
        if (! is_array($json)) {
            return '/';
        }

        $invoice = A::get($json, 'data', $json);
        $invitation = null;
        foreach (A::get($invoice, 'invitations', []) as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            if (A::get($candidate, 'link') || A::get($candidate, 'key')) {
                $invitation = $candidate;
                break;
            }
        }

        $link = is_array($invitation) ? (A::get($invitation, 'link') ?: $this->portalLinkFromKey(strval(A::get($invitation, 'key')))) : null;

        return parent::checkout() && $link ? $link : '/';
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }

    public function handleWebhook(array $payload, array $headers = []): WebhookResult
    {
        $headers = array_change_key_case($headers, CASE_LOWER);
        $eventId = $this->firstValue([
            A::get($payload, 'event_id'),
            A::get($payload, 'id'),
            A::get($payload, 'activity_id'),
            A::get($payload, 'data.id'),
            A::get($payload, 'data.event_id'),
            A::get($headers, 'x-event-id'),
            A::get($headers, 'x-invoiceninja-event'),
        ]);
        $eventId = $eventId ? strval($eventId) : null;

        if ($eventId && $this->isDuplicateWebhook($eventId)) {
            return WebhookResult::ignored('duplicate webhook');
        }

        if (! $this->verifySignature($payload, $headers)) {
            return WebhookResult::invalid('invalid signature');
        }

        $event = $this->firstValue([
            A::get($payload, 'event'),
            A::get($payload, 'event_name'),
            A::get($payload, 'type'),
            A::get($payload, 'activity'),
        ]);
        $event = $event ? strtolower(strval($event)) : null;
        $allowed = $this->option('webhook_events');
        if (is_array($allowed) && ! empty($allowed) && $event && ! in_array($event, array_map(
            static fn ($value) => strtolower(strval($value)),
            $allowed
        ), true)) {
            return WebhookResult::ignored('event not handled');
        }

        $invoice = $this->firstArray([
            A::get($payload, 'invoice'),
            A::get($payload, 'data.invoice'),
            A::get($payload, 'data.data.invoice'),
        ]);
        if (! $invoice && ($invoiceId = $this->firstValue([
            A::get($payload, 'invoice_id'),
            A::get($payload, 'invoiceId'),
            A::get($payload, 'data.invoice_id'),
            A::get($payload, 'data.invoice.id'),
            A::get($payload, 'data.payment.invoice_id'),
            A::get($payload, 'invoice.id'),
        ]))) {
            $invoice = $this->fetchInvoice(strval($invoiceId));
        }

        if (! is_array($invoice)) {
            return WebhookResult::invalid('invoice missing');
        }

        if (! $this->isInvoicePaid($invoice)) {
            return WebhookResult::ignored('invoice not paid');
        }

        $orderData = $this->buildOrderData($invoice, $payload);

        if ($eventId) {
            $this->rememberWebhook($eventId);
        }

        return WebhookResult::ok($orderData, $event ?: 'invoice.ninja.webhook');
    }

    public function fetchProducts(): array
    {
        $products = [];
        $page = 1;

        while (true) {
            // https://api-docs.invoiceninja.com/#/products/getProducts
            $remote = Remote::get($this->endpoint().'/products', [
                'headers' => $this->headers(),
                'data' => [
                    'per_page' => 200,
                    'page' => $page,
                ],
            ]);

            $json = $remote->code() === 200 ? $remote->json() : null;
            if (! is_array($json)) {
                break;
            }

            foreach (A::get($json, 'data', []) as $product) {
                $products[A::get($product, 'id')] = $product;
            }

            $total = intval(A::get($json, 'meta.pagination.total_pages', 1));
            if ($page >= $total) {
                break;
            }
            $page++;
        }

        return array_map(fn (array $data) => (new VirtualPage(
            $data,
            [
                'id' => 'id',
                'title' => fn ($i) => A::get($i, 'product_key', A::get($i, 'id')),
                'content' => [
                    'description' => 'notes',
                    'price' => fn ($i) => floatval(A::get($i, 'price', A::get($i, 'cost', 0))),
                    'tags' => fn ($i) => A::get($i, 'custom_value1', ''),
                    'categories' => fn ($i) => A::get($i, 'custom_value2', ''),
                ],
            ],
            $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), $products);
    }

    private function verifySignature(array $payload, array $headers): bool
    {
        $secret = strval($this->option('webhook_secret') ?? '');
        if (! $secret) {
            return true; // opt-out: treat as valid when no secret configured
        }

        $signature = A::get($headers, 'x-api-signature') ??
            A::get($headers, 'x-ninja-signature') ??
            A::get($headers, 'x-invoiceninja-signature') ??
            A::get($headers, 'x-signature');

        if (! $signature) {
            return false;
        }

        $encoded = strval($payload['_raw'] ?? '');
        if (! $encoded) {
            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if (! $encoded) {
            return false;
        }

        return hash_equals($signature, hash_hmac('sha256', $encoded, $secret));
    }

    private function fetchInvoice(string $invoiceId): ?array
    {
        // https://api-docs.invoiceninja.com/#/invoices/getInvoice
        $remote = Remote::get($this->endpoint().'/invoices/'.$invoiceId, [
            'headers' => $this->headers(),
            'data' => [
                'include' => 'client,client.contacts',
            ],
        ]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        $invoice = is_array($json) ? A::get($json, 'data', $json) : null;

        return is_array($invoice) ? $invoice : null;
    }

    private function isInvoicePaid(array $invoice): bool
    {
        $amount = floatval(A::get($invoice, 'amount', 0));
        $balance = floatval(A::get($invoice, 'balance', 0));
        $paid = floatval(A::get($invoice, 'paid_to_date', 0));
        $statusId = intval(A::get($invoice, 'status_id', 0));

        if ($amount > 0 && $balance <= 0.0) {
            return true;
        }

        if ($paid >= $amount && $amount > 0) {
            return true;
        }

        // Invoice Ninja paid/partial statuses are >= 4
        return in_array($statusId, [4, 5, 6], true);
    }

    private function buildOrderData(array $invoice, array $payload): array
    {
        $client = A::get($invoice, 'client', []);
        $contacts = A::get($client, 'contacts', []);

        $contactEmail = null;
        foreach ($contacts as $contact) {
            if ($contactEmail = A::get($contact, 'email')) {
                break;
            }
        }

        $payment = $this->firstArray([
            A::get($payload, 'payment'),
            A::get($payload, 'data.payment'),
            A::get($payload, 'data.data.payment'),
        ]) ?? [];

        $email = $contactEmail ??
            A::get($client, 'email') ??
            A::get($invoice, 'customer.email') ??
            A::get($payload, 'customer.email');

        $name = A::get($client, 'name') ??
            trim((A::get($contacts, '0.first_name', '').' '.A::get($contacts, '0.last_name', '')));

        $items = $this->mapLineItems(A::get($invoice, 'line_items', []), $invoice, $payment);

        $subtotal = floatval(A::get($invoice, 'amount', 0));
        $tax = floatval(A::get($invoice, 'total_taxes', A::get($invoice, 'tax_amount', 0)));
        $discount = floatval(A::get($invoice, 'discount', 0));

        return array_filter([
            'invoiceId' => A::get($invoice, 'id'),
            'invoiceNumber' => A::get($invoice, 'number'),
            'currency' => A::get($invoice, 'currency_id'),
            'email' => $email,
            'customer' => array_filter([
                'id' => A::get($client, 'id'),
                'email' => $email,
                'name' => $name,
            ]),
            'paidDate' => $this->normalizeDate(
                A::get($payment, 'date') ??
                A::get($payment, 'created_at') ??
                A::get($invoice, 'paid_at') ??
                A::get($invoice, 'date')
            ),
            'paymentMethod' => A::get($payment, 'type') ?? A::get($payment, 'gateway') ?? A::get($payment, 'payment_method'),
            'paymentId' => A::get($payment, 'transaction_reference') ?? A::get($payment, 'id'),
            'paymentComplete' => $this->isInvoicePaid($invoice),
            'invoiceurl' => A::get($invoice, 'download_link') ?? A::get($invoice, 'pdf_url') ?? A::get($invoice, 'url'),
            'items' => $items,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => floatval(A::get($invoice, 'amount', $subtotal)),
        ], fn ($value) => $value !== null && $value !== '' && ! (is_array($value) && empty($value)));
    }

    private function mapLineItems(array $lineItems, array $invoice, array $payment = []): array
    {
        $items = [];
        $uuid = kart()->option('products.product.uuid');
        $license = kart()->option('licenses.license.uuid');

        foreach ($lineItems as $line) {
            $quantity = floatval(A::get($line, 'quantity', 1));
            $price = floatval(A::get($line, 'cost', A::get($line, 'price', 0)));
            $total = floatval(A::get($line, 'line_total', $price * $quantity));
            $tax = floatval(A::get($line, 'tax', A::get($line, 'tax_total', 0)));
            $discount = floatval(A::get($line, 'discount', A::get($line, 'discount_total', 0)));
            $productId = strval(A::get($line, 'product_id', A::get($line, 'product_key', A::get($line, 'id', ''))));

            $key = [];
            if ($productId && $uuid instanceof \Closure) {
                $key = ['page://'.$uuid(null, ['id' => $productId])];
            }

            $licenseKey = null;
            if ($license instanceof \Closure) {
                $licenseKey = $license([
                    'invoice' => $invoice,
                    'payment' => $payment,
                    'line' => $line,
                ]);
            }

            $items[] = array_filter([
                'key' => $key,
                'variant' => A::get($line, 'custom_value1', ''),
                'quantity' => $quantity,
                'price' => $price,
                'total' => $total,
                'subtotal' => floatval(A::get($line, 'subtotal', $total)),
                'tax' => $tax,
                'discount' => $discount,
                'licensekey' => $licenseKey,
            ], fn ($value) => $value !== null && $value !== '' && ! (is_array($value) && empty($value)));
        }

        return $items;
    }

    private function firstValue(array $candidates): mixed
    {
        foreach ($candidates as $candidate) {
            if ($candidate) {
                return $candidate;
            }
        }

        return null;
    }

    private function firstArray(array $candidates): ?array
    {
        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizeDate(int|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $timestamp = intval($value);
            if ($timestamp > 0 && $timestamp < 10_000_000_000) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }

        $time = strtotime(strval($value));

        return $time ? date('Y-m-d H:i:s', $time) : null;
    }

    private function endpoint(): string
    {
        $endpoint = strval($this->option('endpoint'));

        return rtrim($endpoint ?: 'https://app.invoicing.co/api/v1', '/');
    }

    private function portalLinkFromKey(string $invitationKey): ?string
    {
        if ($invitationKey === '') {
            return null;
        }

        $base = rtrim(Str::before($this->endpoint(), '/api'), '/');
        if ($base === '') {
            $base = 'https://app.invoicing.co';
        }

        return $base.'/client/invoice/'.$invitationKey;
    }

    private function headers(bool $json = false): array
    {
        $headers = [
            'Authorization' => 'Bearer '.strval($this->option('token')),
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];

        if ($json) {
            $headers['Content-Type'] = 'application/json';
        }

        if ($company = $this->option('company_key')) {
            $headers['X-Company-Token'] = $company;
        }

        return $headers;
    }
}
