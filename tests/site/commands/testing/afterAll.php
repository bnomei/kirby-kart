<?php

require_once __DIR__.'/../../../Testing.php';

return [
    'description' => 'After all',
    'command' => function ($cli) {
        Testing::afterAll();
    },
];
