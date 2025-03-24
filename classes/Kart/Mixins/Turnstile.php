<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart\Mixins;

use Kirby\Http\Remote;

trait Turnstile
{
    public static function hasTurnstile(?string $response = null): ?int
    {
        $secretkey = kart()->option('turnstile.secretkey');
        if (! $secretkey) {
            return null;
        }

        $response = $response ?? get('cf-turnstile-response');
        if (empty($response)) {
            return null;
        }

        $response = Remote::post(
            kart()->option('turnstile.endpoint'),
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Charset: utf8',
                ],
                'data' => [
                    'response' => $response,
                    'secret' => $secretkey,
                ],
            ]);

        $ok = $response->code() === 200 && $response->json()['success'] === true;

        return $ok ? null : 401;
    }
}
