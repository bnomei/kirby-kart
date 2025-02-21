<?php

use Bnomei\Kart\Helper;
use Kirby\Cms\Page;
use Kirby\Content\Field;
use Kirby\Toolkit\Str;

/**
 * @method Field invnumber()
 * @method Field paidDate()
 * @method Field customer()
 * @method Field items()
 * @method Field paymentComplete()
 * @method Field paymentMethod()
 */
class OrderPage extends Page
{
    public static function create(array $props): Page
    {
        // enforce unique but short slug with the option to overwrite it in a closure
        $uuid = kirby()->option('bnomei.kart.orders.order.uuid');
        if ($uuid instanceof Closure) {
            $uuid = $uuid(kart()->page(\Bnomei\Kart\ContentPageEnum::ORDERS), $props);
            $props['slug'] = Str::slug(str_replace('or_', '', $uuid));
            $props['content']['uuid'] = $uuid;
            $props['content']['title'] = strtoupper($uuid);
        }

        /** @var OrderPage $p */
        $p = parent::create($props);
        $p = $p->updateInvoiceNumber();

        return $p->changeStatus('unlisted');
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
            'create' => [
                'title' => 'auto',
                'slug' => 'auto',
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
                        ],
                        [
                            'label' => t('kart.items', 'Item(s)'),
                            'value' => '{{ page.items.toStructure.count }}',
                        ],
                    ],
                ],
                'meta' => [
                    'type' => 'fields',
                    'fields' => [
                        'customer' => [
                            'label' => t('kart.customer', 'Customer'),
                            'type' => 'users',
                            'multiple' => false,
                            // 'query' => 'kirby.users.filterBy("role", "customer")',
                            'translate' => false,
                            'width' => '1/2',
                        ],
                        'invnumber' => [
                            'label' => t('kart.invoiceNumber', 'Invoice Number'),
                            'type' => 'number',
                            'min' => 1,
                            'step' => 1,
                            // 'default' => 1, // Do not do this. Messes with auto-incrementing.
                            // 'required' => true,
                            'translate' => false,
                            'width' => '1/2',
                        ],
                        'paymentComplete' => [
                            'label' => t('kart.paymentcomplete', 'Payment Complete'),
                            'type' => 'toggle',
                            'width' => '1/3',
                            'text' => [
                                ['en' => 'No', 'de' => 'Nein'],
                                ['en' => 'Yes', 'de' => 'Ja'],
                            ],
                            'translate' => false,
                        ],
                        'paymentMethod' => [
                            'label' => t('kart.paymentmethod', 'Payment Method'),
                            'type' => 'text',
                            'width' => '1/3',
                            'translate' => false,
                        ],
                        'paidDate' => [ // Merx 1.7+ https://github.com/wagnerwagner/merx/blob/8cadc64a0c4e98144c33b476094601560f204191/models/orderPageAbstract.php#L76C25-L76C33
                            'label' => t('kart.paidDate', 'Paid Date'),
                            'type' => 'date',
                            'required' => true,
                            'time' => true,
                            'default' => 'now',
                            'translate' => false,
                            'width' => '1/3',
                        ],
                        'line' => [
                            'type' => 'line',
                        ],
                        'items' => [ // use `items` for Merx compatibility
                            'label' => t('kart.products', 'Products'),
                            'type' => 'structure',
                            'translate' => false,
                            'fields' => [
                                'key' => [ // use `key` for Merx compatibility, `id` breaks Structures
                                    'label' => t('kart.product', 'Product'),
                                    'type' => 'pages',
                                    'query' => 'site.kart.page("products")',
                                    // 'required' => true, // dont require in case Merx import fails
                                    'multiple' => false,
                                    'subpages' => false,
                                ],
                                'price' => [
                                    'label' => t('kart.price', 'Price'),
                                    'type' => 'number',
                                    'required' => true,
                                    'min' => 0,
                                    'step' => 0.01,
                                    'default' => 0,
                                ],
                                'quantity' => [
                                    'label' => t('kart.quantity', 'Quantity'),
                                    'type' => 'number',
                                    'required' => true,
                                    'min' => 1,
                                    'step' => 1,
                                    'default' => 1,
                                ],
                                'total' => [ // merx compat would be price
                                    'label' => t('kart.total', 'Total'),
                                    'type' => 'number',
                                    'required' => true,
                                    'min' => 0,
                                    'step' => 0.01,
                                    'default' => 0,
                                ],
                                'subtotal' => [
                                    'label' => t('kart.subtotal', 'Subtotal'),
                                    'type' => 'number',
                                    'required' => true,
                                    'min' => 0,
                                    'step' => 0.01,
                                    'default' => 0,
                                ],
                                'tax' => [
                                    'label' => t('kart.tax', 'Tax'),
                                    'type' => 'number',
                                    'required' => true,
                                    'min' => 0,
                                    'step' => 0.01,
                                    'default' => 0,
                                ],
                                'discount' => [
                                    'label' => t('kart.discount', 'Discount'),
                                    'type' => 'number',
                                    'required' => true,
                                    'min' => 0,
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

    public function hasProduct(string|ProductPage $key): bool
    {
        return $this->productsCount($key) > 0;
    }

    public function productsCount(string|ProductPage|null $key = null): int
    {
        if ($key instanceof ProductPage) {
            $key = $key->id();
        }

        $sum = 0;
        foreach ($this->items()->toStructure() as $item) {
            // it does not matter if id or uuid is stored with this query
            if (! $key || $item->key()->toPage()?->id() === $key || $item->key()->toPage()?->uuid()->toString() === $key) {
                $sum += $item->quantity()->toInt();
            }
        }

        return $sum;
    }

    // TODO: this does not match STRIPE aka the TOTAL. it would be the SUBTOTAL
    public function sum(): float
    {
        $sum = 0.0;
        foreach ($this->items()->toStructure() as $item) {
            $sum += $item->price()->toFloat() * $item->quantity()->toFloat();
        }

        return (float) $sum;
    }

    // TODO: this does not match STRIPE
    public function tax(): float
    {
        $tax = 0;
        foreach ($this->items()->toStructure() as $item) {
            $tax += ($item->price()->toFloat() * $item->tax()->toFloat() / 100.0) * $item->quantity()->toFloat();
        }

        return (float) $tax;
    }

    public function sumtax(): float
    {
        return $this->sum() + $this->tax();
    }

    public function formattedSum(): string
    {
        return Helper::formatCurrency($this->sum());
    }

    public function formattedTax(): string
    {
        return Helper::formatCurrency($this->tax());
    }

    public function formattedSumTax(): string
    {
        return Helper::formatCurrency($this->sumtax());
    }

    public function invoiceNumber(): string
    {
        // $page = $this->updateInvoiceNumber(); // this would auto-fix Merx pages but it's not needed otherwise

        return str_pad($this->invnumber()->value(), 5, 0, STR_PAD_LEFT);
    }

    /*
     * takes care of migrating the Merx invoiceNumber from their
     * virtual 0000x of $page->num to a persisted value.
     */
    public function updateInvoiceNumber(): Page
    {
        $page = $this;
        $current = $page->invnumber()->isEmpty() ? null : $page->invnumber()->toInt();

        // if this order does have a num (from Merx) use that
        if ($page->num() !== null) {
            $current = $page->num();
            if ($this->invnumber()->toInt() !== $current) {
                $page = $this->kirby()->impersonate('kirby', function () use ($page, $current) {
                    return $page->update([
                        'invnumber' => $current,
                    ]);
                });
            }
        }

        // if the current is higher than the tracker in the parent then update the parent with current
        if ($current && $page->parent()->invnumber()->toInt() <= $current) {
            $this->kirby()->impersonate('kirby', function () use ($page, $current) {
                $page->parent()->update([
                    'invnumber' => $current,
                ]);
            });
        }

        // if the order does not have an invoice number increment and fetch from parent
        if ($page->invnumber()->isEmpty()) {
            $page = $this->kirby()->impersonate('kirby', function () use ($page) {
                $next = $page->parent()->increment('invnumber', 1)->invnumber()->toInt();

                return $page->update([
                    'invnumber' => $next,
                ]);
            });
        }

        return $page;
    }
}
