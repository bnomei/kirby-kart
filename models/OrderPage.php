<?php

use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Kart;
use Bnomei\Kart\OrderLine;
use Kirby\Cms\Collection;
use Kirby\Cms\File;
use Kirby\Cms\Files;
use Kirby\Cms\Page;
use Kirby\Content\Field;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\Str;

/**
 * @method Field invoiceurl()
 * @method Field invnumber()
 * @method Field paidDate()
 * @method Field customer()
 * @method Field items()
 * @method Field paymentComplete()
 * @method Field paymentMethod()
 * @method Field notes()
 */
class OrderPage extends Page
{
    public static function create(array $props): Page
    {
        $parent = kart()->page(ContentPageEnum::ORDERS);

        // enforce unique but short slug with the option to overwrite it in a closure
        $uuid = kart()->option('orders.order.uuid');
        if ($uuid instanceof Closure) {
            $uuid = $uuid($parent, $props);
            $props['slug'] = Str::slug($uuid);
            $props['content']['uuid'] = $uuid;
            $props['content']['title'] = strtoupper($uuid);
        }

        $props['parent'] = $parent;
        $props['isDraft'] = false;
        $props['template'] = kart()->option('orders.order.template', 'order');
        $props['model'] = kart()->option('orders.order.model', 'order');

        /** @var OrderPage $p */
        $p = parent::create($props);

        return $p->updateInvoiceNumber();
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
                    'label' => 'bnomei.kart.summary',
                    'size' => 'huge',
                    'type' => 'stats',
                    'reports' => [
                        [
                            // 'label' => 'bnomei.kart.invoiceNumber', Invoice Number'),
                            'value' => '#{{ page.invoiceNumber }}',
                            'info' => '{{ page.paidDate.toDate("Y-m-d H:i") }}',
                        ],
                        [
                            // 'label' => 'bnomei.kart.sum', Sum'),
                            'value' => '{{ page.formattedSubtotal }}',
                            'info' => '+ {{ page.formattedTax }}',
                        ],
                        [
                            'label' => t('bnomei.kart.items'),
                            'value' => '{{ page.items.toStructure.count }}',
                        ],
                    ],
                ],
                'order' => [
                    'type' => 'fields',
                    'fields' => [
                        'customer' => [
                            'label' => 'bnomei.kart.customer',
                            'type' => 'users',
                            'multiple' => false,
                            // 'query' => 'kirby.users.filterBy("role", "customer")',
                            'translate' => false,
                            'width' => '1/2',
                        ],
                        'invnumber' => [
                            'label' => 'bnomei.kart.invoiceNumber',
                            'type' => 'number',
                            'min' => 1,
                            'step' => 1,
                            // 'default' => 1, // Do not do this. Messes with auto-incrementing.
                            // 'required' => true,
                            'translate' => false,
                            'width' => '1/2',
                        ],
                        'paymentComplete' => [
                            'label' => 'bnomei.kart.paymentcomplete',
                            'type' => 'toggle',
                            'width' => '1/3',
                            'text' => [
                                ['en' => 'No', 'de' => 'Nein'],
                                ['en' => 'Yes', 'de' => 'Ja'],
                            ],
                            'translate' => false,
                        ],
                        'paymentMethod' => [
                            'label' => 'bnomei.kart.paymentmethod',
                            'type' => 'text',
                            'width' => '1/3',
                            'translate' => false,
                        ],
                        'paidDate' => [ // Merx 1.7+ https://github.com/wagnerwagner/merx/blob/8cadc64a0c4e98144c33b476094601560f204191/models/orderPageAbstract.php#L76C25-L76C33
                            'label' => 'bnomei.kart.paidDate',
                            'type' => 'date',
                            'required' => true,
                            'time' => true,
                            'default' => 'now',
                            'translate' => false,
                            'width' => '1/3',
                        ],
                        'invoiceurl' => [
                            'label' => 'bnomei.kart.invoice',
                            'type' => 'url',
                            'translate' => false,
                        ],
                        'line' => [
                            'type' => 'line',
                        ],
                        'items' => [ // use `items` for Merx compatibility
                            'label' => 'bnomei.kart.items',
                            'type' => 'structure',
                            'translate' => false,
                            'fields' => [
                                'key' => [ // use `key` for Merx compatibility, `id` breaks Structures
                                    'label' => 'bnomei.kart.product',
                                    'type' => 'pages',
                                    'query' => 'site.kart.page("products")',
                                    'multiple' => false,
                                    'subpages' => false,
                                ],
                                'price' => [ // Merx
                                    'label' => 'bnomei.kart.price',
                                    'type' => 'number',
                                    'min' => 0,
                                    'step' => 0.01,
                                    'default' => 0,
                                ],
                                'quantity' => [ // Merx
                                    'label' => 'bnomei.kart.quantity',
                                    'type' => 'number',
                                    'min' => 1,
                                    'step' => 1,
                                    'default' => 1,
                                ],
                                'total' => [ // (price * quantity - discount) * tax
                                    'label' => 'bnomei.kart.total',
                                    'type' => 'number',
                                    'min' => 0,
                                    'step' => 0.01,
                                    'default' => 0,
                                ],
                                'subtotal' => [ // (price * quantity - discount)
                                    'label' => 'bnomei.kart.subtotal',
                                    'type' => 'number',
                                    'min' => 0,
                                    'step' => 0.01,
                                    'default' => 0,
                                ],
                                'tax' => [ // plain tax value (not taxrate)
                                    'label' => 'bnomei.kart.tax',
                                    'type' => 'number',
                                    'min' => 0,
                                    'step' => 0.01,
                                    'default' => 0,
                                ],
                                'discount' => [ // total discount applied to price * quantity
                                    'label' => 'bnomei.kart.discount',
                                    'type' => 'number',
                                    'min' => 0,
                                    'step' => 0.01,
                                    'default' => 0,
                                ],
                            ],
                        ],
                        'line2' => [
                            'type' => 'line',
                        ],
                    ],
                ],
                'files' => [
                    'type' => 'files',
                    'info' => '{{ file.niceSize }} ・ {{ file.modifiedAt }}',
                ],
                'meta' => [
                    'type' => 'fields',
                    'fields' => [
                        'note' => [
                            'label' => 'bnomei.kart.note',
                            'type' => 'textarea',
                            'translate' => false,
                            'buttons' => false,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function hasProduct(string|ProductPage $key): bool
    {
        return $this->productsCount($key, true) > 0;
    }

    public function productsCount(string|ProductPage|null $key = null, bool $oneIsEnough = false): int
    {
        if ($key instanceof ProductPage) {
            $key = $key->id();
        }

        $sum = 0;
        foreach ($this->items()->toStructure() as $item) {
            // it does not matter if id or uuid is stored with this query
            if (! $key || $item->key()->toPage()?->id() === $key || $item->key()->toPage()?->uuid()->toString() === $key) {
                $sum += $item->quantity()->toInt();
                if ($oneIsEnough) {
                    return $sum;
                }
            }
        }

        return $sum;
    }

    public function itemsSum(string $field): float
    {
        $sum = 0.0;
        foreach ($this->items()->toStructure() as $item) {
            $sum += $item->$field()->toFloat();
        }

        return (float) $sum;
    }

    /**
     * @kql-allowed
     */
    public function total(): float
    {
        return $this->itemsSum('total');
    }

    /**
     * @kql-allowed
     */
    public function sumtax(): float // Merx
    {
        return $this->itemsSum('total');
    }

    /**
     * @kql-allowed
     */
    public function discount(): float
    {
        return $this->itemsSum('discount');
    }

    /**
     * @kql-allowed
     */
    public function subtotal(): float
    {
        return $this->itemsSum('subtotal');
    }

    /**
     * @kql-allowed
     */
    public function sum(): float // Merx
    {
        return $this->itemsSum('subtotal');
    }

    /**
     * @kql-allowed
     */
    public function tax(): float
    {
        return $this->itemsSum('tax'); // this in NOT Merx compatible, which stored taxrate
    }

    /**
     * @kql-allowed
     */
    public function formattedDiscount(): string
    {
        return Kart::formatCurrency($this->discount());
    }

    /**
     * @kql-allowed
     */
    public function formattedTotal(): string
    {
        return Kart::formatCurrency($this->total());
    }

    /**
     * @kql-allowed
     */
    public function formattedSubtotal(): string
    {
        return Kart::formatCurrency($this->subtotal());
    }

    /**
     * @kql-allowed
     */
    public function formattedSum(): string
    {
        return Kart::formatCurrency($this->sum());
    }

    /**
     * @kql-allowed
     */
    public function formattedTax(): string
    {
        return Kart::formatCurrency($this->tax());
    }

    /**
     * @kql-allowed
     */
    public function formattedSumTax(): string
    {
        return Kart::formatCurrency($this->sumtax());
    }

    /**
     * @kql-allowed
     */
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
        $pageId = $this->id();
        $current = $page->invnumber()->isEmpty() ? null : $page->invnumber()->toInt();

        // if this order does have a num (from Merx) use that
        if ($page->num() !== null) {
            $current = $page->num();
            if ($this->invnumber()->toInt() !== $current) {
                $this->kirby()->impersonate('kirby', function () use ($pageId, $current) {
                    return page($pageId)->update([
                        'invnumber' => $current,
                    ]);
                });
            }
        }

        // if the current is higher than the tracker in the parent then update the parent with current
        if ($current && $page->parent()->invnumber()->toInt() <= $current) {
            $this->kirby()->impersonate('kirby', function () use ($pageId, $current) {
                page($pageId)->parent()->update([
                    'invnumber' => $current,
                ]);
            });
        }

        // if the order does not have an invoice number increment and fetch from parent
        if ($page->invnumber()->isEmpty()) {
            $page = $this->kirby()->impersonate('kirby', function () use ($pageId) {
                $next = page($pageId)->parent()->increment('invnumber', 1)->invnumber()->toInt();

                return page($pageId)->update([
                    'invnumber' => $next,
                ]);
            });
        }

        return $page;
    }

    /**
     * @kql-allowed
     */
    public function invoice(): string
    {
        return $this->invoiceurl()->isNotEmpty() ? $this->invoiceurl()->value() : $this->url().'.pdf';
    }

    /**
     * @kql-allowed
     */
    public function download(): ?string
    {
        // append time to allow for easier tracking and cache busting
        return $this->downloads() && $this->isPayed() ? $this->url().'.zip?token='.time() : null;
    }

    public function downloads(): ?File
    {
        $file = $this->files()
            ->filterBy('extension', 'zip')
            ->sortBy('modified', 'desc')
            ->first();

        if (! $file && $this->kart()->option('orders.order.create-missing-zips')) {
            $file = $this->createZipWithFiles();
        }

        return $file;
    }

    public function createZipWithFiles(Files|array $files = [], ?string $zipFilename = null): ?File
    {
        $tmpId = date('U-v');
        $tmpDir = kirby()->roots()->cache().'/zips/'.$tmpId;
        Dir::make($tmpDir);

        if ($files instanceof Files) {
            foreach ($files as $file) {
                F::copy($file->root(), $tmpDir.'/'.$file->filename());
            }
        }

        foreach ($this->items()->toStructure() as $item) {
            foreach ($item->key()->toPage()?->downloads()->toFiles() as $file) {
                F::copy($file->root(), $tmpDir.'/'.$file->filename());
            }
        }

        if (is_array($files)) {
            foreach ($files as $file) {
                F::copy($file, $tmpDir.'/'.basename($file));
            }
        }

        $existingFiles = Dir::read($tmpDir);

        if (count($existingFiles) === 0) {
            Dir::remove($tmpDir);

            return null;
        }

        if (count($existingFiles) === 1 && pathinfo($existingFiles[0], PATHINFO_EXTENSION) === 'zip') {
            $zipFile = $tmpDir.'/'.$existingFiles[0];
        } else {
            $zipFile = $tmpDir.'.zip';
            $zip = new ZipArchive;
            if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
                foreach (Dir::read($tmpDir) as $file) {
                    $filePath = $tmpDir.'/'.$file;
                    if (is_file($filePath)) {
                        $zip->addFile($filePath, $this->slug().'/'.$file);
                        $zip->setCompressionName($file, ZipArchive::CM_STORE); // store is quickest
                    }
                }
                $zip->close();
            }
        }

        $file = kirby()->impersonate('kirby', function () use ($zipFile, $zipFilename, $tmpId) {
            return $this->createFile([
                'filename' => $zipFilename ?? md5($tmpId).'.zip', // make unguessable
                'source' => $zipFile,
            ], move: true);
        });

        Dir::remove($tmpDir);

        return $file;
    }

    /**
     * @kql-allowed
     */
    public function isPayed(): bool
    {
        return $this->paymentComplete()->toBool();
    }

    /**
     * @return Collection<string, OrderLine>
     */
    public function orderLines(): Collection
    {
        $lines = [];
        foreach ($this->items()->toStructure() as $line) {
            $lines[] = new OrderLine(
                $line->key()->toPage()?->uuid()->toString(),
                $line->price()->toFloat(),
                $line->quantity()->toInt(),
                $line->total()->toFloat(),
                $line->subtotal()->toFloat(),
                $line->tax()->toFloat(),
                $line->discount()->toFloat()
            );
        }

        return new Collection($lines);
    }
}
