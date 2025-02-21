<?php

return [
    'editor' => 'phpstorm',
    'debug' => true,
    'content' => [
        'locking' => false,
    ],

    'cache' => [
        'uuid' => false, // TODO: make this a TIP in the readme that it helps to avoid issues while figuring out the mapping of uuids in virtual pages. one might end up with an uuid cache pointing to a very different page otherwise
    ],

    'tests.frontend' => 'kart/html', // html htmx datastar

    // 'bnomei.kart.expire' => null, // disable caching

    'bnomei.kart.provider' => \Bnomei\Kart\Provider\Stripe::class,

    'bnomei.kart.providers.stripe.secret_key' => fn () => trim(file_get_contents(__DIR__.'/../../.env.stripe.secret_key')),

    'bnomei.kart.router.encryption' => false,
];
