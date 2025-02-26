<?php

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
                            'info' => '{{ page.children.sortBy("paidDate", "desc").first.paidDate }}',
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

    /**
     * @kql-allowed
     */
    public function withProduct(ProductPage|string|null $product): Pages
    {
        return $this->children()->filterBy(
            fn (OrderPage $orderPage) => $orderPage->hasProduct($product)
        );
    }

    /**
     * @kql-allowed
     */
    public function withCustomer(User|string|null $user): Pages
    {
        if (is_string($user)) {
            $user = $this->kirby()->users()->findBy('email', $user);
        }

        return $this->children()->filterBy(
            fn (OrderPage $orderPage) => $user && $orderPage->customer()->toUser()?->is($user)
        );
    }

    /**
     * @kql-allowed
     */
    public function withInvoiceNumber(int|string $invoiceNumber): ?Page
    {
        if (is_string($invoiceNumber)) {
            $invoiceNumber = ltrim($invoiceNumber, '0');
        }

        return $this->children()->filterBy(
            fn (OrderPage $orderPage) => $orderPage->invnumber()->toInt() === $invoiceNumber
        )->first();
    }

    public function createOrder(array $data, ?User $customer): ?Page
    {
        if (! $this->kirby()->option('bnomei.kart.orders.enabled')) {
            return null;
        }

        return OrderPage::create([
            // id, title, slug and uuid are automatically generated
            'content' => A::get($data, [
                'paidDate',
                'paymentMethod',
                'paymentComplete',
                'items',
            ]) + [
                'customer' => [$customer?->uuid()->toString()], // kirby user field expects an array
            ],
        ]);
    }
}
