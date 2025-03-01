<?php

use Kirby\Cms\Page;
use Kirby\Cms\Pages;

class ProductsPage extends Page
{
    public static function phpBlueprint(): array
    {
        return [
            'name' => 'products',
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
                    'link' => '{< site.kart.sync("products") >}',
                ],
                'status' => true,
            ],
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
                    'create' => ! defined('KART_PRODUCTS_UPDATE') || constant('KART_PRODUCTS_UPDATE') === false,
                    'sortable' => ! defined('KART_PRODUCTS_UPDATE') || constant('KART_PRODUCTS_UPDATE') === false,
                    'template' => 'product', // maps to ProductPage model
                    'info' => '{{ page.formattedPrice }}',
                    'image' => [
                        'query' => 'page.gallery.first.toFile',
                    ],
                ],
                'files' => [
                    'type' => 'files',
                    'info' => '{{ file.dimensions }} ・ {{ file.niceSize }}',
                    'layout' => 'cardlets',
                    'image' => [
                        'cover' => true,
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

        return $this->children = parent::children()->merge(
            Pages::factory(kart()->provider()->products(), $this)
        );
    }

    /**
     * @todo Not implemented
     */
    public function withPriceId(string $priceId): ?ProductPage
    {
        return $this->children()
            ->filterBy(fn (ProductPage $p) => in_array($priceId, $p->priceIds()))->first();
    }

    /**
     * @kql-allowed
     */
    public function withoutStocks(): Pages
    {
        return $this->children()->filterBy(fn (ProductPage $page) => ! is_numeric($page->stock()));
    }

    /**
     * @kql-allowed
     */
    public function withCategory(string|array $category, bool $any = true): Pages
    {
        if (is_string($category)) {
            $category = [$category];
        }

        return $any ? $this->children()->filterBy('categories', 'in', $category, ',') :
            $this->children()->filterBy(fn ($product) => count(array_diff($category, $product->categories()->split())) === 0);
    }

    /**
     * @kql-allowed
     */
    public function withTag(string|array $tags, bool $any = true): Pages
    {
        if (is_string($tags)) {
            $tags = [$tags];
        }

        return $any ? $this->children()->filterBy('tags', 'in', $tags, ',') :
            $this->children()->filterBy(fn ($product) => count(array_diff($tags, $product->tags()->split())) === 0);
    }
}
