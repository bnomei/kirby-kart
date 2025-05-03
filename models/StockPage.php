<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Kart;
use Kirby\Cms\Page;
use Kirby\Content\Field;
use Kirby\Data\Yaml;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

/**
 * @method Field page()
 * @method Field stock()
 * @method Field variants()
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
            $props['content']['title'] = strtoupper((string) $uuid);
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
            'image' => [
                'back' => 'var(--color-black)',
                'color' => 'var(--color-gray-500)',
                'cover' => true,
                'icon' => 'kart-stock',
                'query' => false,
            ],
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
                    'query' => 'site.kart.page("products")', // TODO: .withoutStocks does not work
                    // 'required' => true,
                    'info' => '{{ page.formattedPrice }}{{ page.featured.ecco(" ★") }}{{ page.variants.ecco(" ❖") }}',
                    // 'layout' => 'cards',
                    'multiple' => false,
                    'subpages' => false,
                    'translate' => false,
                    'width' => '1/4',
                    'image' => [
                        'cover' => true,
                        'query' => 'page.gallery.toFiles.first',
                    ],
                ],
                'timestamp' => [
                    'label' => 'bnomei.kart.timestamp',
                    'type' => 'date',
                    'required' => true,
                    'time' => true,
                    'default' => 'now',
                    'translate' => false,
                    'width' => '1/4',
                ],
                'gap1' => [
                    'type' => 'gap',
                    'width' => '1/2',
                ],
                'stock' => [
                    'label' => 'bnomei.kart.stock',
                    'type' => 'number',
                    'required' => true,
                    // 'min' => 0, // allow stock to be negative when updating from orders
                    'step' => 1,
                    'default' => 0,
                    'translate' => false,
                    'width' => '1/4',
                ],
                'variants' => [
                    'label' => 'bnomei.kart.variants',
                    'type' => 'structure',
                    'after' => '{{ kirby.option("bnomei.kart.currency") }}',
                    'translate' => false,
                    'width' => '3/4',
                    'fields' => [
                        'variant' => [
                            'label' => 'bnomei.kart.variant',
                            'type' => 'tags',
                        ],
                        'stock' => [
                            'label' => 'bnomei.kart.stock',
                            'type' => 'number',
                            // 'min' => 0,
                            'step' => 1,
                            'default' => 0,
                        ],
                    ],
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
        /** @var ProductPage $product */
        $product = $this->page()->toPage();
        $stock = $product?->stockWithVariants();
        if (is_string($stock)) {
            return $stock;
        }

        return str_pad((string) $stock, $length, '0', STR_PAD_LEFT);
    }

    public function updateStock(int $amount = 0, bool $queue = true, bool $set = false, ?string $variant = null): ?int
    {
        if ($amount === 0 && ! $set) {
            return 0;
        }

        if ($queue && kart()->option('stocks.queue')) {
            kart()->queue()->push([
                'page' => $this->uuid()->toString(),
                'method' => 'updateStock',
                'data' => [
                    'amount' => $amount,
                    'queue' => false,
                    'set' => $set,
                    'variant' => $variant,
                ],
            ]);

            return null;
        }

        return $this->kirby()->impersonate('kirby', function () use ($amount, $set, $variant) {
            $stock = $this;
            $foundVariant = false;
            if ($variant) {
                $updated = [];
                /** @var \Kirby\Cms\StructureObject $variantItem */
                foreach ($stock->variants()->toStructure() as $variantItem) {
                    $variants = $variantItem->variant()->split();
                    sort($variants);
                    $v = implode(',', $variants); // no whitespace
                    if ($v === $variant) {
                        $u = $set ?
                            array_merge($variantItem->toArray(), ['stock' => $amount]) :
                            array_merge($variantItem->toArray(), ['stock' => $variantItem->stock()->toInt() + $amount]);
                        unset($u['id']);
                        $updated[] = $u;
                        $foundVariant = true;
                    } else {
                        $updated[] = $variantItem->toArray(); // keep the original
                    }
                }

                $stock = $stock->update(['variants' => Yaml::encode($updated)]);
            }
            if (! $foundVariant) {
                $stock = $set ?
                    $stock->update(['stock' => $amount]) :
                    $stock->increment('stock', $amount);
            }

            kirby()->trigger('kart.stocks.updated', [
                'stock' => $stock,
                'amount' => $amount,
                'variant' => $variant,
            ]);

            return $stock;
        })->stockFromVariant($variant)->toInt();
    }

    public function stockFromVariant(?string $variant = null): Field
    {
        if ($variant) {
            foreach ($this->variants()->toStructure() as $variantItem) {
                if ($variantItem->variant()->value() === $variant) {
                    return new Field($this, 'stock_'.Kart::hash($variant),
                        $variantItem->stock()->isNotEmpty() ? $variantItem->stock()->toInt() : null
                    );
                }
            }
        }

        return $this->stock();
    }
}
