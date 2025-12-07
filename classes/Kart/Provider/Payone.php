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

class Payone extends Provider
{
    protected string $name = ProviderEnum::PAYONE->value;

    public function checkout(): string
    {
        // NOTE: webhook-only integration; Payone redirect/callback handling is expected via webhook rather than polling.
        return parent::checkout();
    }

    public function completed(array $data = []): array
    {
        // Payone redirect responses are handled via webhooks/callback; nothing to fetch here.
        $this->kirby->session()->remove('bnomei.kart.'.$this->name.'.session_id');

        return parent::completed($data);
    }

    public function fetchProducts(): array
    {
        // Payone is PSP-only, no catalog.
        return [];
    }

    private function endpoint(): string
    {
        $endpoint = strval($this->option('endpoint'));

        return $endpoint ?: 'https://api.pay1.de/post-gateway/';
    }
}
