<?php

use Bnomei\Kart\Data;
use Kirby\Cms\Page;
use Kirby\Content\Field;

/**
 * @method Field invnumber()
 * @method Field paidDate()
 * @method Field customer()
 * @method Field items()
 */
class OrderPage extends Page
{
    public static function create(array $props): Page
    {
        // enforce unique but short slug with the option to overwrite it in a closure
        $props['slug'] = kirby()->option('bnomei.kart.orders.slug', $props['slug']);
        if ($props['slug'] instanceof Closure) {
            $props['slug'] = $props['slug'](kart()->page('orders'), $props);
            $props['content']['uuid'] = $props['slug'];
            $props['content']['title'] = strtoupper($props['slug']);
        }

        return parent::create($props);
    }

    public static function phpBlueprint(): array
    {
        return [
            'name' => 'order',
            'options' => [
                'changeSlug' => false,
                'changeTitle' => false,
                'changeTemplate' => false,
            ],
            'sections' => [
                'stats' => [
                    'label' => t('kart.summary', 'Summary'),
                    'size' => 'huge',
                    'type' => 'stats',
                    'reports' => [
                        [
                            // 'label' => t('kart.invoiceNumber', 'Invoice Number'),
                            'value' => '#{{ page.invoiceNumber }}',
                            'info' => '{{ page.paidDate }}',
                        ],
                        [
                            // 'label' => t('kart.sum', 'Sum'),
                            'value' => '{{ page.formattedSum }}',
                            'info' => '+ {{ page.formattedTax }}',
                            'theme' => 'neutral',
                        ],
                        [
                            'label' => t('kart.items', 'Items'),
                            'value' => '{{ page.items.toStructure.count }}',
                        ],
                    ],
                ],
                'meta' => [
                    'type' => 'fields',
                    'fields' => [
                        'line' => [
                            'type' => 'line',
                        ],
                        'invnumber' => [
                            'label' => t('kart.invoiceNumber', 'Invoice Number'),
                            'type' => 'number',
                            'min' => 1,
                            'step' => 1,
                            'default' => 1,
                            // 'required' => true,
                            'translate' => false,
                        ],
                        'paidDate' => [ // Merx 1.7+ https://github.com/wagnerwagner/merx/blob/8cadc64a0c4e98144c33b476094601560f204191/models/orderPageAbstract.php#L76C25-L76C33
                            'label' => t('kart.paidDate', 'Paid Date'),
                            'type' => 'date',
                            'required' => true,
                            'time' => true,
                            'default' => 'now',
                            'translate' => false,
                        ],
                        'customer' => [
                            'label' => t('kart.customer', 'Customer'),
                            'type' => 'users',
                            'multiple' => false,
                            'query' => 'kirby.users.filterBy("role", "customer")',
                            'translate' => false,
                        ],
                        'items' => [ // use `items` for Merx compatibility
                            'label' => t('kart.products', 'Products'),
                            'type' => 'structure',
                            'translate' => false,
                            'fields' => [
                                'id' => [ // use `id` for Merx compatibility
                                    'label' => t('kart.product', 'Product'),
                                    'type' => 'pages',
                                    'query' => 'site.kart.page("products")',
                                    // 'required' => true, // dont require in case Merx import fails
                                    'multiple' => false,
                                    'subpages' => false,
                                ],
                                'quantity' => [
                                    'label' => t('kart.quantity', 'Amount'),
                                    'type' => 'number',
                                    'required' => true,
                                    'min' => 1,
                                    'step' => 1,
                                    'default' => 1,
                                ],
                                'price' => [
                                    'label' => t('kart.price', 'Price'),
                                    'type' => 'number',
                                    'required' => true,
                                    'min' => 0,
                                    'step' => 0.01,
                                    'default' => 0,
                                ],
                                'tax' => [ // aka taxrate
                                    'label' => t('kart.tax', 'Tax'),
                                    'type' => 'number',
                                    'required' => true,
                                    'min' => 0,
                                    'max' => 100,
                                    'step' => 0.01,
                                    'default' => 0,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function formattedSum(): string
    {
        $sum = 0;
        foreach ($this->items()->toStructure() as $item) {
            $sum += $item->price()->toFloat() * $item->quantity()->toFloat();
        }

        return Data::formatCurrency($sum);
    }

    public function formattedTax(): string
    {
        $tax = 0;
        foreach ($this->items()->toStructure() as $item) {
            $tax += ($item->price()->toFloat() * $item->tax()->toFloat() / 100.0) * $item->quantity()->toFloat();
        }

        return Data::formatCurrency($tax);
    }

    public function formattedSumTax(): string
    {
        $sumtax = 0;
        foreach ($this->items()->toStructure() as $item) {
            $sumtax += ($item->price()->toFloat() * (1.0 + $item->tax()->toFloat() / 100.0)) * $item->quantity()->toFloat();
        }

        return Data::formatCurrency($sumtax);
    }

    public function invoiceNumber(): string
    {
        $page = $this->updateInvoiceNumber();

        return str_pad($page->invnumber()->value(), 5, 0, STR_PAD_LEFT);
    }

    /*
     * takes care of migrating the Merx invoiceNumber from their
     * virtual 0000x of $page->num to a persisted value.
     */
    public function updateInvoiceNumber(): Page
    {
        $page = $this;
        if ($this->invnumber()->isEmpty()) {
            $page = $this->kirby()->impersonate('kirby', function () {
                $current = $this->num();
                if ($this->parent()->invnumber()->toInt() < $current) {
                    $this->parent()->update([
                        'invnumber' => $current,
                    ]);
                }

                return $this->update([
                    'invnumber' => $current,
                ]);
            });
        }

        return $page;
    }

    public function incrementInvoiceNumber(): Page
    {
        $page = $this;
        if ($this->invnumber()->isEmpty()) {
            $page = $this->kirby()->impersonate('kirby', function () {
                $next = $this->parent()->increment('invnumber')->invnumber()->toInt();

                return $this->update([
                    'invnumber' => $next,
                ]);
            });
        }

        return $page;
    }
}
