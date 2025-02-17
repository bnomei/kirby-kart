<?php

use Kirby\Cms\Page;

/**
 * @method \Kirby\Content\Field invoiceNumber()
 */
class OrdersPage extends Page
{
    public static function phpBlueprint(): array
    {
        return [
            'name' => 'orders',
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
                'stats' => [
                    'type' => 'stats',
                    'reports' => [
                        [
                            'label' => t('kart.sum', 'Sum'),
                            'value' => '{{ page.formattedSum }}',
                        ],
                    ],
                ],
                'meta' => [
                    'type' => 'fields',
                    'fields' => [
                        'invoiceNumber' => [
                            'label' => t('kart.invoiceNumber', 'Invoice Number'),
                            'type' => 'number',
                            'min' => 1,
                            'step' => 1,
                            'default' => 1,
                            'required' => true,
                            'translate' => false,
                        ],
                    ],
                ],
                'orders' => [
                    'label' => t('kart.orders', 'Orders'),
                    'type' => 'pages',
                    'template' => 'order',
                    'sortBy' => 'paidDate desc',
                    'text' => '#{{ page.invoiceNumber }}',
                    'info' => '{{ page.formattedSum }} {{ page.payedDate }}',
                ],
            ],
        ];
    }
}
