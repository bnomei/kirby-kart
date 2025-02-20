<?php

use Kirby\Cms\Page;
use Kirby\Cms\Pages;

/**
 * @method \Kirby\Content\Field invnumber()
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
                    'label' => t('kart.summary', 'Summary'),
                    'type' => 'stats',
                    'reports' => [
                        [
                            'label' => t('kart.latest', 'Latest'),
                            'value' => '{{ page.children.sortBy("paidDate", "desc").first.paidDate }}',
                        ],
                        [
                            // 'label' => t('kart.sum', 'Sum'),
                            'value' => '{{ page.children.sumField("sum").toFormattedCurrency }}',
                            'info' => '+ {{ page.children.sumField("tax").toFormattedCurrency }}',
                        ],
                        [
                            'label' => t('kart.orders', 'Orders'),
                            'value' => '{{ page.children.count }}',
                        ],
                    ],
                ],
                'meta' => [
                    'type' => 'fields',
                    'fields' => [
                        'invnumber' => [
                            'label' => t('kart.latestinvoiceNumber', 'Lastest Invoice Number'),
                            'type' => 'number',
                            'min' => 1,
                            'step' => 1,
                            'default' => 1,
                            'required' => true,
                            'translate' => false,
                        ],
                        'line' => [
                            'type' => 'line',
                        ],
                    ],
                ],
                'orders' => [
                    'label' => t('kart.orders', 'Orders'),
                    'type' => 'pages',
                    'search' => true,
                    'template' => 'order', // maps to OrderPage model
                    'sortBy' => 'invnumber desc',
                    'text' => '[#{{ page.invoiceNumber }}] {{ page.customer.toUser.email }} ・ {{ page.formattedSum }} + {{ page.formattedTax }}',
                    'info' => '{{ page.title }} ・ {{ page.paidDate }}',
                ],
            ],
        ];
    }

    public function children(): Pages
    {
        if ($this->children instanceof Pages) {
            return $this->children;
        }

        return $this->children = parent::children()->merge(
            Pages::factory(kart()->provider()->orders(), $this)
        );
    }
}
