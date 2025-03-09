<?php

namespace Bnomei\Kart\Mixins;

use Kirby\Http\Remote;

trait Turnstile
{
    public static function hasTurnstile(?string $response = null): ?bool
    {
        $secretkey = kart()->option('turnstile.secretkey');
        if (! $secretkey) {
            return null;
        }

        $response = $response ?? get('cf-turnstile-response');
        if (empty($response)) {
            return false;
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

        return $response->code() === 200 && $response->json()['success'] === true;
    }
}
