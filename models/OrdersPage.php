<?php

use Kirby\Cms\Page;

class OrdersPage extends Page
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
            'sections' => [
                'orders' => [
                    'label' => t('kart.orders', 'Orders'),
                    'type' => 'pages',
                    'template' => 'orders',
                ],
            ],
        ];
    }
}
