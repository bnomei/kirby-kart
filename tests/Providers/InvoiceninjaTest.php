<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Provider\Invoiceninja;
use Bnomei\Kart\WebhookResult;

uses()->group('providers');

require_once __DIR__.'/../Helpers.php';

beforeEach(function (): void {
    findOrCreateTestUser();
    $this->provider = new Invoiceninja(kirby());
});

it('requires webhook secret for webhook verification', function (): void {
    kart()->setOption('providers.invoice_ninja.webhook_secret', '');

    $payload = [
        'event' => 'invoice.paid',
        'invoice' => [
            'id' => 'inv_test',
            'amount' => 10,
            'balance' => 0,
            'line_items' => [],
            'client' => [
                'email' => 'customer@kart.test',
            ],
        ],
        '_raw' => '{"event":"invoice.paid"}',
    ];

    $result = $this->provider->handleWebhook($payload, [
        'x-api-signature' => 'not-valid',
    ]);

    expect($result->status)->toBe(WebhookResult::STATUS_INVALID);
});
