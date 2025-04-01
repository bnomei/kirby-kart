<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\User;
use Kirby\Content\Field;
use Kirby\Toolkit\A;

/**
 * @method Field invnumber()
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
                    'label' => t('bnomei.kart.summary'),
                    'type' => 'stats',
                    'reports' => [
                        [
                            'label' => 'bnomei.kart.latest-order',
                            'value' => '#{{ page.children.sortBy("paidDate", "desc").first.invoiceNumber }} ・ {{ page.children.sortBy("paidDate", "desc").first.customer.toUser.email }}',
                            'info' => '{{ page.children.sortBy("paidDate", "desc").first.paidDate.toDate("Y-m-d H:i") }}',
                            'link' => '{{ page.children.sortBy("paidDate", "desc").first.panel.url }}',
                        ],
                        [
                            'label' => 'bnomei.kart.revenue-30',
                            'value' => '{{ page.children.trend("paidDate", "sum").toFormattedCurrency }}',
                            'info' => '{{ page.children.trendPercent("paidDate", "sum").toFormattedNumber(true) }}%',
                            'theme' => '{{ page.children.trendTheme("paidDate", "sum") }}',
                        ],
                        [
                            'label' => 'bnomei.kart.orders-30',
                            'value' => '{{ page.children.interval("paidDate", "-30 days", "now").count }}',
                            'info' => '{{ page.children.interval("paidDate", "-60 days", "-31 days").count }}',
                        ],
                    ],
                ],
                'meta' => [
                    'type' => 'fields',
                    'fields' => [
                        'invnumber' => [
                            'label' => 'bnomei.kart.latest-invoice-number',
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
                    'label' => 'bnomei.kart.orders',
                    'type' => 'pages',
                    'search' => true,
                    'template' => 'order', // maps to OrderPage model
                    'sortBy' => 'invnumber desc',
                    'text' => '[#{{ page.invoiceNumber }}] {{ page.customer.toUser.email }} ・ {{ page.formattedSubtotal }}',
                    'info' => '{{ page.title }} ・ {{ page.paidDate.toDate("Y-m-d H:i") }}',
                ],
            ],
        ];
    }

    /*
     * @todo
     */
    public function children(): Pages
    {
        return parent::children();

        /*
        if ($this->children instanceof Pages) {
            return $this->children;
        }

        return $this->children = parent::children()->merge(
            Pages::factory(kart()->provider()->orders(), $this)
        );
        */
    }

    public function createOrder(array $data, ?User $customer = null): ?Page
    {
        if (! $this->kart()->option('orders.enabled')) {
            return null;
        }

        return kirby()->impersonate('kirby', fn () => OrderPage::create([
            // id, title, slug and uuid are automatically generated
            'content' => A::get($data, [
                'paidDate',
                'paymentMethod',
                'paymentComplete',
                'items',
            ]) + [
                'customer' => [$customer?->uuid()->toString()], // kirby user field expects an array
            ],
        ]));
    }
}
