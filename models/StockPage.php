<?php
/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\ContentPageEnum;
use Kirby\Cms\Page;
use Kirby\Content\Field;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

/**
 * @method Field page()
 * @method Field stock()
 * @method Field timestamp()
 */
class StockPage extends Page
{
    public static function create(array $props): Page
    {
        $parent = kart()->page(ContentPageEnum::STOCKS);

        // enforce unique but short slug with the option to overwrite it in a closure
        $uuid = kart()->option('stocks.stock.uuid');
        if ($uuid instanceof Closure) {
            $uuid = $uuid($parent, $props);
            $props['slug'] = Str::slug($uuid);
            $props['content']['uuid'] = $uuid;
            $props['content']['title'] = strtoupper($uuid);
        }

        $props['parent'] = $parent;
        $props['isDraft'] = false;
        $props['template'] = kart()->option('stocks.stock.template', 'stock');
        $props['model'] = kart()->option('stocks.stock.model', 'stock');

        return parent::create($props);
    }

    public static function phpBlueprint(): array
    {
        return [
            'name' => 'stock',
            'options' => [
                'changeSlug' => false,
                'changeTitle' => false,
                'changeTemplate' => false,
            ],
            'create' => [
                'title' => 'auto',
                'slug' => 'auto',
            ],
            'fields' => [
                'page' => [
                    'label' => 'bnomei.kart.product',
                    'type' => 'pages',
                    'query' => 'site.kart.page("demo/products")', // TODO: .withoutStocks does not work
                    // 'required' => true,
                    'multiple' => false,
                    'subpages' => false,
                    'translate' => false,
                ],
                'stock' => [
                    'label' => 'bnomei.kart.stock',
                    'type' => 'number',
                    'required' => true,
                    // 'min' => 0, // allow stock to be negative when updating from orders
                    'step' => 1,
                    'default' => 0,
                    'translate' => false,
                ],
                'timestamp' => [
                    'label' => 'bnomei.kart.timestamp',
                    'type' => 'date',
                    'required' => true,
                    'time' => true,
                    'default' => 'now',
                    'translate' => false,
                ],
            ],
        ];
    }

    public function onlyOneStockPagePerProduct(array $values): bool
    {
        $productUuid = A::get($values, 'page');
        if (! empty($productUuid) && is_array($productUuid)) {
            $productUuid = $productUuid[0];
        }

        return $this->parent()
            ->childrenAndDrafts()
            ->not($this)
            ->filterBy(fn ($page) => $page->page()->toPages()->count() &&
                $page->page()->toPage()?->uuid()->toString() === $productUuid
            )->count() === 0;
    }

    /**
     * @kql-allowed
     */
    public function stockPad(int $length): string
    {
        return str_pad($this->stock()->value(), $length, '0', STR_PAD_LEFT);
    }

    public function updateStock(int $amount = 0, bool $queue = true): ?int
    {
        if ($amount === 0) {
            return 0;
        }

        if ($queue && kart()->option('stocks.queue')) {
            kart()->queue()->push([
                'page' => $this->uuid()->toString(),
                'method' => 'updateStock',
                'payload' => [
                    'amount' => $amount,
                    'queue' => false,
                ],
            ]);

            return null;
        }

        return $this->kirby()->impersonate('kirby', function () use ($amount) {
            $stock = $this->increment('stock', $amount);
            kirby()->trigger('kart.stocks.updated', [
                'stock' => $stock,
                'amount' => $amount,
            ]);

            return $stock;
        })->stock()->toInt();
    }
}
