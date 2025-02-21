<?php

use Kirby\Cms\Page;
use Kirby\Cms\Pages;

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
                    'label' => t('kart.summary', 'Summary'),
                    'type' => 'stats',
                    'reports' => [
                        [
                            'label' => t('kart.products', 'Products'),
                            'value' => '{{ page.children.count }}',
                            'link' => '{{ site.kart.page("products").panel.url }}',
                        ],
                        [
                            'label' => t('kart.stocks', 'Stocks'),
                            'value' => '{{ page.children.sumField("stock").toInt }}',
                        ],
                        [
                            'label' => t('kart.latest', 'Latest'),
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
                    'label' => t('kart.stocks', 'Stocks'),
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

    public function stockPages(?string $id = null): Pages
    {
        return $this->children()
            ->filterBy(fn ($page) => $page->page()->toPage()?->uuid()->toString() === $id);
    }

    public function stock(?string $id = null): int
    {
        return $this->stockPages($id)->sumField('stock')->toInt();
    }
}
