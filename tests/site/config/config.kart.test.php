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
        'uuid' => false, // TODO: make this a TIP in the readme that it helps to avoid issues while figuring out the mapping of uuids in virtual pages. one might end up with an uuid cache pointing to a very different page otherwise
    ],

    // Test suite related options
    'tests.frontend' => 'kart/html', // html htmx datastar

    // KART options used in the tests
    // 'bnomei.kart.expire' => null, // disable caching

    // 'bnomei.kart.provider' => 'stripe',

    'bnomei.kart.providers.stripe.secret_key' => fn () => trim(file_get_contents(__DIR__.'/../../.env.stripe.secret_key')),
    // 'bnomei.kart.router.encryption' => false,
];
