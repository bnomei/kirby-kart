<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

class WebhookResult
{
    public const STATUS_OK = 'ok';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_IGNORED = 'ignored';

    public function __construct(
        public readonly string $status,
        public readonly ?string $message = null,
        public readonly array $orderData = [],
    ) {}

    public static function ok(array $orderData = [], ?string $message = null): self
    {
        return new self(self::STATUS_OK, $message, $orderData);
    }

    public static function invalid(?string $message = null): self
    {
        return new self(self::STATUS_INVALID, $message);
    }

    public static function ignored(?string $message = null): self
    {
        return new self(self::STATUS_IGNORED, $message);
    }

    public function isOk(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    public function isInvalid(): bool
    {
        return $this->status === self::STATUS_INVALID;
    }

    public function isIgnored(): bool
    {
        return $this->status === self::STATUS_IGNORED;
    }
}
