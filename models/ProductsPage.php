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
use Kirby\Content\Field;

class ProductsPage extends Page
{
    public static function phpBlueprint(): array
    {
        return [
            'name' => 'products',
            'image' => [
                'back' => 'var(--color-black)',
                'color' => 'var(--color-gray-500)',
                'cover' => true,
                'icon' => 'kart-store',
                'query' => false,
            ],
            'options' => [
                'changeSlug' => false,
                'changeTemplate' => false,
                'delete' => false,
                'duplicate' => false,
                'move' => false,
            ],
            'buttons' => [
                'preview' => true,
                'sync' => [
                    'icon' => 'refresh',
                    'text' => 'bnomei.kart.sync-provider',
                    'link' => '{< site.kart.urls.sync("products") >}',
                ],
                'status' => true,
            ],
            'tabs' => [
                'overview' => [
                    'sections' => [
                        'stats' => [
                            'label' => 'bnomei.kart.summary',
                            'type' => 'stats',
                            'reports' => [
                                [
                                    'label' => 'bnomei.kart.products',
                                    'value' => '{{ page.children.count }}',
                                ],
                                [
                                    'label' => 'bnomei.kart.out-of-stock',
                                    'value' => '{{ page.outOfStock.count }}',
                                    'link' => '{{ site.kart.page("stocks")?.panel.url }}',
                                ],
                                [
                                    'label' => 'bnomei.kart.provider',
                                    'value' => '{{ site.kart.provider.title }}',
                                ],
                                [
                                    'label' => 'bnomei.kart.last-sync',
                                    'value' => '{{ site.kart.provider.updatedAt("products") }}',
                                ],
                            ],
                        ],
                        'meta' => [
                            'type' => 'fields',
                            'fields' => [
                                'categories' => [
                                    'label' => '{{ t("bnomei.kart.categories") }} ({{ site.kart.categories.count }})',
                                    'type' => 'allcategories',
                                    'disabled' => true,
                                    'translate' => false,
                                    'width' => '1/3',
                                ],
                                'tags' => [
                                    'label' => '{{ t("bnomei.kart.tags") }} ({{ site.kart.tags.count }})',
                                    'type' => 'alltags',
                                    'disabled' => true,
                                    'translate' => false,
                                    'width' => '2/3',
                                ],
                                'line' => [
                                    'type' => 'line',
                                ],
                            ],
                        ],
                        'products' => [
                            'label' => 'bnomei.kart.products',
                            'type' => 'pages',
                            'layout' => 'cards',
                            'search' => true,
                            'template' => 'product', // maps to ProductPage model
                            'info' => '{{ page.formattedPrice }} [{{ page.stock }}]{{ page.inStock ? "" : " ⚠️" }}{{ page.featured.ecco(" ★") }}{{ page.variants.ecco(" ❖") }}',
                            'image' => [
                                'cover' => true,
                                'query' => 'page.gallery.first.toFile',
                            ],
                            'limit' => 1000,
                            'sortable' => false,
                            'sortBy' => 'title asc',
                        ],
                    ],
                ],
                'files' => [
                    'sections' => [
                        'files' => [
                            'type' => 'files',
                            'info' => '{{ file.dimensions }} ・ {{ file.niceSize }}',
                            'layout' => 'cards',
                            'image' => [
                                'cover' => true,
                            ],
                            'limit' => 1000,
                            'search' => true,
                            'sortable' => false,
                            'sortBy' => 'filename asc',
                        ],
                    ],
                ],
            ],

        ];
    }

    public function children(): Pages
    {
        if ($this->children instanceof Pages) {
            return $this->children;
        }

        $this->children = parent::children();
        $uuids = $this->children->toArray(fn ($p) => $p->uuid()->id());

        $this->kirby()->impersonate('kirby', function () use ($uuids): void {
            $uuid = kart()->option('products.product.uuid');
            foreach (kart()->provider()->products() as $product) {
                if (in_array($uuid($this, $product), $uuids)) {
                    continue;
                }
                $this->createChild($product);
            }
        });

        return $this->children;
    }

    public function categories(): Field
    {
        return new Field($this, 'categories', implode(',', kart()->allCategories()));
    }

    public function tags(): Field
    {
        return new Field($this, 'tags', implode(',', kart()->allTags()));
    }

    public function outOfStock(): Pages
    {
        return $this->children()->filterBy(function (ProductPage $p) {
            $stock = $p->stockWithVariants();
                return !(is_string($stock) || $stock > 0);
        });
    }
}
