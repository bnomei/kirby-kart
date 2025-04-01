<?php

require_once __DIR__.'/../../../Testing.php';

return [
    'description' => 'Before all',
    'command' => function ($cli) {
        Testing::beforeAll();
    },
];
