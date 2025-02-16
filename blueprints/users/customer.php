<?php

// https://getkirby.com/docs/guide/users/permissions
return [
    'title' => 'Kart Customer',
    'icon' => 'cart',
    'permissions' => [
        'access' => [
            'panel' => false, // lock the user out of the panel
        ],
        'files' => true, // can manipulate files
        'language' => false,
        'pages' => true, // can manipulate pages, like orders
        'site' => false,
        'user' => true, // can update itself
        'users' => false,
    ],
];
