<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;
use Kirby\Toolkit\A;

class Checkout extends Provider
{
    protected string $name = ProviderEnum::CHECKOUT->value;

    public function checkout(): string
    {
        // NOTE: webhook-only integration; Kart relies on Checkout.com webhooks for completion and does not poll or redirect.
        return parent::checkout();
    }

    public function completed(array $data = []): array
    {
        // Hosted payments rely on webhooks for finalization; nothing to pull here.
        // Keep the interface consistent.
        $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.session_id');

        return parent::completed($data);
    }

    public function fetchProducts(): array
    {
        // Checkout.com acts as a PSP without a product catalog; return empty list.
        return [];
    }

    private function endpoint(): string
    {
        $endpoint = strval($this->option('endpoint'));

        return rtrim($endpoint ?: 'https://api.sandbox.checkout.com', '/');
    }

    private function headers(bool $json = false): array
    {
        $headers = [
            'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            'Accept' => 'application/json',
        ];

        if ($json) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }
}
