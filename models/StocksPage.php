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
            'image' => [
                'back' => 'var(--color-black)',
                'color' => 'var(--color-gray-500)',
                'cover' => true,
                'icon' => 'kart-stocks',
                'query' => false,
            ],
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
                            'link' => '{{ site.kart.page("products")?.panel.url }}',
                        ],
                        [
                            'label' => 'bnomei.kart.out-of-stock',
                            'value' => '{{ site.kart.page("products")?.outOfStock.count }}',
                        ],
                        [
                            'label' => 'bnomei.kart.stocks',
                            'value' => '{{ page.stock(null, null, "*") }}', // everything
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
                    // 'search' => true,
                    'template' => 'stock', // maps to StockPage model
                    'sortBy' => 'timestamp desc',
                    'text' => '{{ page.page.toPage.inStock ? "" : "⚠️ " }}[{{ page.stockPad(3) }}] {{ page.page.toPage.title }}',
                    'info' => '{{ page.title }} ・ {{ page.timestamp }}',
                    'limit' => 1000,
                ],
            ],
        ];
    }

    /**
     * @kql-allowed
     */
    public function stock(?string $id = null, ?string $withHold = null, ?string $variant = null): ?int
    {
        $expire = kart()->option('expire');
        if (is_int($expire)) {
            $stocks = $this->kirby()->cache('bnomei.kart.stocks')->getOrSet('stocks', function () {
                $stocks = [];
                $t = 0;
                /** @var StockPage $stockPage */
                foreach ($this->stockPages() as $stockPage) {
                    $c = 0;
                    $page = $stockPage->page()->toPage();
                    if (! $page) {
                        continue;
                    }
                    $p = $stockPage->stock()->toInt();
                    $stocks[$page->uuid()->toString()] = $p;
                    $t += $p;
                    foreach ($stockPage->variants()->toStructure() as $var) {
                        $v = $var->variant()->split();
                        sort($v);
                        $v = implode(',', $v); // no whitespace
                        if ($var->stock()->isNotEmpty()) {
                            $stocks[$page->uuid()->toString().'|'.$v] = $var->stock()->toInt();
                            $c += $var->stock()->toInt();
                        }
                    }
                    $t += $c;
                    if ($c > 0) {
                        $stocks[$page->uuid()->toString().'|*'] = $c;
                    }
                    if ($p + $c > 0) {
                        $stocks[$page->uuid()->toString().'|='] = $p + $c;
                    }
                }
                if ($t > 0) {
                    $stocks['|*'] = $t; // null id and all variants AKA everything
                }

                return $stocks;
            }, $expire);

            $stock = A::get($stocks, $id.($variant ? '|'.$variant : ''));
        } else {
            // slowish...
            $stocks = $this->stockPages($id);
            $stock = $stocks->count() && ! $variant ? $stocks->sumField('stock')->toInt() : null;
            foreach ($stocks as $p) {
                foreach ($p->variants()->toStructure() as $var) {
                    $v = $var->variant()->split();
                    sort($v);
                    $v = implode(',', $v); // no whitespace
                    if ($variant && ($v === $variant || $variant === '*')) {
                        if ($stock === null) {
                            $stock = 0;
                        }
                        $stock += $var->stock()->toInt();
                    }
                }
            }
        }

        if ($stock === null) {
            return null;
        }

        // decrement by stock in hold
        if ($withHold && $id && kart()->option('stocks.hold')) {
            foreach ($this->kirby()->cache('bnomei.kart.stocks-holds')->get('hold-'.Kart::hash($id), []) as $sid => $hold) {
                if (strval($sid) === $withHold) {

                    continue; // ignore own holds
                }
                if ($hold['expires'] < time()) {
                    continue; // will be removed on next set
                }
                $stock -= $hold['quantity'];
            }
        }

        return $stock;
    }

    /**
     * @kql-allowed
     */
    public function stockPages(ProductPage|string|null $id = null): Pages
    {
        $c = $this->children();
        if ($id !== null) {
            if ($id instanceof ProductPage) {
                $id = $id->uuid()->toString();
            }
            $c = $c->filterBy(fn ($page) => $page->page()->toPage()?->uuid()->toString() === $id);
        }

        return $c;
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
            Pages::factory(kart()->provider()->stocks(), $this)
        );
        */
    }

    public function updateStocks(array $data, int $mod = 1): ?int
    {
        $count = 0;
        if ($mod >= 1) {
            $mod = 1;
        } else {
            $mod = -1;
        }
        foreach (A::get($data, 'items', []) as $item) {
            if (! is_array($item['key']) || count($item['key']) !== 1) {
                continue;
            }

            /** @var ?ProductPage $product */
            $product = $this->kirby()->page(strval($item['key'][0]));
            if (! $product) {
                continue;
            }

            if ($this->updateStock($product, intval($item['quantity']) * $mod, false, A::get($item, 'variant')) !== null) {
                $count++;
            }
        }

        return kart()->option('stocks.queue') ? null : $count;
    }

    public function updateStock(ProductPage $product, int $quantity, bool $set = false, ?string $variant = null): ?int
    {
        /** @var StockPage $stockPage */
        $stockPage = $this->stockPages($product->uuid()->toString())->first();
        if (! $stockPage) {
            return null;
        }

        return $stockPage->updateStock($quantity, true, $set, $variant);
    }
}
