<?php

return [
    'editor' => 'phpstorm',
    'debug' => true,
    'languages' => false,
    'content' => [
        'locking' => false,
    ],

    'bnomei.api-pages.records' => [
        'rickandmorty' => [ // site/models/rickandmorty.php & site/blueprints/pages/rickandmorty.yml
            'url' => 'https://rickandmortyapi.com/graphql', // string or closure
            'params' => [
                'headers' => function (\Bnomei\APIRecords $records) {
                    // you could add Basic/Bearer Auth within this closure if you needed
                    // or retrieve environment variable with `env()` and use them here
                    return [
                        'Content-Type: application/json',
                    ];
                },
                'method' => 'POST', // defaults to GET else provide a string or closure
                'data' => json_encode(['query' => '{ characters() { results { name status species }}}']), // string or closure
            ],
            'query' => 'data.characters.results', // {"data: [...]}
            'map' => [
                // kirby <=> json
                'title' => 'name',
                'uuid' => fn ($i) => md5($i['name']),
                'template' => fn ($i) => strtolower($i['species']), // site/blueprints/pages/alien.yml || human.yml
                'content' => [
                    'species' => 'species',
                    'hstatus' => 'status', // status is reserved by kirby
                ],
            ],
        ],
    ],
];
