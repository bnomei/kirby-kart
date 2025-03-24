<?php
/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Kart;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Toolkit\A;

class StocksPage extends Page
{
    public static function phpBlueprint(): array
    {
        return [
            'name' => 'stocks',
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
                    'label' => 'bnomei.kart.summary',
                    'type' => 'stats',
                    'reports' => [
                        [
                            'label' => 'bnomei.kart.products',
                            'value' => '{{ page.children.count }}',
                            'link' => '{{ site.kart.page("products").panel.url }}',
                        ],
                        [
                            'label' => 'bnomei.kart.stocks',
                            'value' => '{{ page.children.sumField("stock").toInt }}',
                        ],
                        [
                            'label' => 'bnomei.kart.latest',
                            'value' => '{{ page.children.sortBy("timestamp", "desc").first.timestamp }}',
                        ],
                    ],
                ],
                'meta' => [
                    'type' => 'fields',
                    'fields' => [
                        'line' => [
                            'type' => 'line',
                        ],
                    ],
                ],
                'stocks' => [
                    'label' => 'bnomei.kart.stocks',
                    'type' => 'pages',
                    'search' => true,
                    'template' => 'stock', // maps to StockPage model
                    'sortBy' => 'timestamp desc',
                    'text' => '[{{ page.stockPad(3) }}] {{ page.page.toPage.title }}',
                    'info' => '{{ page.title }} ・ {{ page.timestamp }}',
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
            Pages::factory(kart()->provider()->stocks(), $this)
        );
    }

    /**
     * @kql-allowed
     */
    public function stockPages(?string $id = null): Pages
    {
        $c = $this->children();
        if ($id !== null) {
            $c = $c->filterBy(fn ($page) => $page->page()->toPage()?->uuid()->toString() === $id);
        }

        return $c;
    }

    /**
     * @kql-allowed
     */
    public function stock(?string $id = null, bool $withHold = false): ?int
    {
        $expire = kart()->option('expire');
        if (is_int($expire)) {
            $stocks = $this->kirby()->cache('bnomei.kart.stocks')->getOrSet('stocks', function () {
                $stocks = [];
                /** @var StockPage $stockPage */
                foreach ($this->stockPages() as $stockPage) {
                    $page = $stockPage->page()->toPage();
                    if (! $page) {
                        continue;
                    }
                    $stocks[$page->uuid()->toString()] = $stockPage->stock()->toInt();
                }

                return $stocks;
            }, $expire);

            $stock = A::get($stocks, $id);
        } else {
            // slowish...
            $stocks = $this->stockPages($id);
            $stock = $stocks->count() ? $stocks->sumField('stock')->toInt() : null;
        }

        if ($stock === null) {
            return null;
        }

        // decrement by stock in hold
        if ($withHold && $id && kart()->option('stocks.hold')) {
            foreach ($this->kirby()->cache('bnomei.kart.stocks')->get('holds-'.Kart::hash($id), []) as $hold) {
                if ($hold['expires'] < time()) {
                    continue; // will be removed on next set
                }
                $stock -= $hold['quantity'];
            }
        }

        return $stock;
    }

    public function updateStocks(array $data): ?int
    {
        $count = 0;
        foreach (A::get($data, 'items', []) as $item) {
            if (! is_array($item['key']) || count($item['key']) !== 1) {
                continue;
            }

            /** @var ?ProductPage $product */
            $product = $this->kirby()->page(strval($item['key'][0]));
            if (! $product) {
                continue;
            }

            if ($this->updateStock($product, intval($item['quantity']) * -1) !== null) {
                $count++;
            }
        }

        return $count > 0;
    }

    public function updateStock(ProductPage $product, int $quantity): ?int
    {
        /** @var StockPage $stockPage */
        $stockPage = $this->stockPages($product->uuid()->toString())->first();
        if (! $stockPage) {
            return null;
        }

        return $stockPage->updateStock($quantity);
    }
}
