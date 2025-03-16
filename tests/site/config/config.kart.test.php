<?php

return [
    'editor' => 'phpstorm',
    'debug' => true,
    'content' => [
        'locking' => false,
    ],
    // https://getkirby.com/docs/cookbook/development-deployment/using-mailhog-for-email-testing
    'email' => [
        'transport' => [
            'type' => 'smtp',
            'host' => 'localhost',
            'port' => 1025,
            'security' => false,
        ],
    ],
    'cache' => [
        'uuid' => false, // when switching providers a lot during dev this avoids mismatches
    ],
    'auth' => [
        'challenge.email.subject' => 'Your login link for {{ site.title }}', // no t() but query support here
        'methods' => ['code', 'kart-magic-link', 'password'], // 'code', 'password', 'password-reset'
    ],

    // /////////////////////////////////
    // KART options used in the tests //
    // /////////////////////////////////

    // 'bnomei.kart.router.encryption' => false,
    // 'bnomei.kart.expire' => null, // disable caching
    // 'bnomei.kart.provider' => 'stripe',

    // barebones single value .env-loaders
    'bnomei.kart.providers.stripe.secret_key' => fn () => trim(file_get_contents(__DIR__.'/../../.env.stripe.secret_key')),

];
