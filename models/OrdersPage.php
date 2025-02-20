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
                            'label' => t('kart.latest', 'Latest Order'),
                            'value' => '#{{ page.children.sortBy("paidDate", "desc").first.invoiceNumber }} ・ {{ page.children.sortBy("paidDate", "desc").first.customer.toUser.email }}',
                            'info' => '{{ page.children.sortBy("paidDate", "desc").first.paidDate }}',
                            'link' => '{{ page.children.sortBy("paidDate", "desc").first.panel.url }}',
                        ],
                        [
                            'label' => t('kart.sum', 'Revenue (30 days)'),
                            'value' => '{{ page.children.trend("paidDate", "sum").toFormattedCurrency }}',
                            'info' => '{{ page.children.trendPercent("paidDate", "sum").toFormattedNumber(true) }}%',
                            'theme' => '{{ page.children.trendTheme("paidDate", "sum") }}',
                        ],
                        [
                            'label' => t('kart.orders', 'Orders (30 days)'),
                            'value' => '{{ page.children.interval("paidDate", "-30 days", "now").count }}',
                            'info' => '{{ page.children.interval("paidDate", "-60 days", "-31 days").count }}',
                        ],
                    ],
                ],
                'meta' => [
                    'type' => 'fields',
                    'fields' => [
                        'invnumber' => [
                            'label' => t('kart.latestinvoiceNumber', 'Latest Invoice Number'),
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
