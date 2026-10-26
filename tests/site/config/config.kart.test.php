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
        'challenges' => ['email', 'kart-magic-link'],
    ],

    // /////////////////////////////////
    // KART options used in the tests //
    // /////////////////////////////////

    // 'bnomei.kart.router.encryption' => false,
    // 'bnomei.kart.expire' => null, // disable caching

    'bnomei.kart.completed' => function (array $data, array $form) {
        // ray($data, $form)->orange();
        return $data;
    },
];
