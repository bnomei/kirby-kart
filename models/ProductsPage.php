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

        $this->children = parent::children();

        $this->kirby()->impersonate('kirby', function () {
            $uuid = kart()->option('products.product.uuid');
            foreach (kart()->provider()->products() as $product) {
                if ($this->children->findByUuid('page://'.$uuid($this, $product))) {
                    continue;
                }
                $this->createChild($product);
            }
        });

        return $this->children;
    }

    /**
     * @todo Not implemented
     */
    public function withPriceId(string $priceId): ?ProductPage
    {
        return $this->children()
            ->filterBy(fn (ProductPage $p) => in_array($priceId, $p->priceIds()))->first();
    }
}
