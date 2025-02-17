<?php

use Kirby\Cms\Page;

class ProductsPage extends Page
{
    public static function phpBlueprint(): array
    {
        return [
            'name' => 'products',
            'options' => [
                'changeSlug' => false,
                'changeTemplate' => false,
                'delete' => false,
                'duplicate' => false,
                'move' => false,
            ],
            'sections' => [
                'stats' => [
                    'label' => t('kart.summary', 'Summary'),
                    'type' => 'stats',
                    'reports' => [
                        [
                            'label' => t('kart.products', 'Products'),
                            'value' => '{{ page.children.count }}',
                        ],
                        [
                            'label' => t('kart.provider', 'Provider'),
                            'value' => '{{ site.kart.provider.title }}',
                        ],
                        [
                            'label' => t('kart.lastsync', 'Last Sync'),
                            'value' => '{{ site.kart.provider.updatedAt }}',
                        ],
                    ],
                ],
                'meta' => [
                    'type' => 'fields',
                    'fields' => [
                        'line' => [
                            'type' => 'line',
                        ],
                    ],
                ],
                'products' => [
                    'label' => t('kart.products', 'Products'),
                    'type' => 'pages',
                    'template' => 'product',
                    'info' => '{{ page.formattedPrice }} + {{ page.tax }}%',
                ],
            ],
        ];
    }
}
