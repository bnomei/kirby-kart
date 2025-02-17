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
                'products' => [
                    'label' => t('kart.products', 'Products'),
                    'type' => 'pages',
                    'template' => 'product',
                    'info' => '[{{ page.stock }}] {{ page.formattedPrice }} + {{ page.formattedTax }}',
                ],
            ],
        ];
    }
}
