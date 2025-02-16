<?php

use Kirby\Cms\Page;

class StocksPage extends Page
{
    public static function phpBlueprint(): array
    {
        return [
            'options' => [
                'preview' => false,
                'changeSlug' => false,
                'changeStatus' => false,
                'changeTemplate' => false,
                'delete' => false,
                'duplicate' => false,
                'move' => false,
                'sort' => false,
            ],
            'fields' => [
                'stocks' => [
                    'label' => t('kart.stocks', 'Stocks'),
                    'type' => 'structure',
                    'fields' => [
                        'page' => [
                            'label' => t('kart.product', 'Product'),
                            'type' => 'pages',
                            'query' => 'kart.page("products")',
                            'required' => true,
                            'multiple' => false,
                            'subpages' => false,
                        ],
                        'stock' => [
                            'label' => t('kart.stock', 'Stock'),
                            'type' => 'number',
                            'required' => true,
                            'min' => 0,
                            'step' => 1,
                            'default' => 0,
                        ],
                        'timestamp' => [
                            'label' => t('kart.timestamp', 'Timestamp'),
                            'type' => 'date',
                            'required' => true,
                            'time' => true,
                            'default' => 'now',
                        ],
                    ],
                ],
            ],
        ];
    }
}
