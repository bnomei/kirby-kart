<?php

use Kirby\Cms\Page;

/**
 * @method \Kirby\Content\Field stocks()
 */
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
                            'value' => '{{ page.stocks.toStructure.count }}',
                        ],
                        [
                            'label' => t('kart.stocks', 'Stocks'),
                            'value' => '{{ page.stocksCount }}',
                        ],
                        [
                            'label' => t('kart.latest', 'Latest'),
                            'value' => '{{ page.stocks.toStructure.sortBy("timestamp", "desc").first.timestamp }}',
                        ],
                    ],
                ],
                'meta' => [
                    'type' => 'fields',
                    'fields' => [
                        'line' => [
                            'type' => 'line',
                        ],
                        'stocks' => [
                            'label' => t('kart.stocks', 'Stocks'),
                            'type' => 'structure',
                            'fields' => [
                                'page' => [
                                    'label' => t('kart.product', 'Product'),
                                    'type' => 'pages',
                                    'query' => 'site.kart.page("products")',
                                    'required' => true,
                                    'multiple' => false,
                                    'subpages' => false,
                                ],
                                'stock' => [
                                    'label' => t('kart.stock', 'Stock'),
                                    'type' => 'number',
                                    'required' => true,
                                    'min' => 0,
                                    'step' => 1,
                                    'default' => 0,
                                ],
                                'timestamp' => [
                                    'label' => t('kart.timestamp', 'Timestamp'),
                                    'type' => 'date',
                                    'required' => true,
                                    'time' => true,
                                    'default' => 'now',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function stock(?string $id): ?int
    {
        $items = $this->stocks()->toStructure();
        if ($id) {
            $id = page($id)?->id();
            $items = $id ? $items->filterBy(fn ($i) => $i->page()->toPage()?->id() === $id) : $items;
        }
        $items = $items->toArray(fn ($i) => intval($i->stock()->toInt()));

        return match (count($items)) {
            0 => null,
            1 => $items[0],
            default => array_sum($items),
        };
    }

    public function stocksCount(): int {}

    public function onlyUniqueProducts(array $stocks): bool
    {
        $pages = array_map(fn ($i) => count($i['stock']) ? page($i['page'][0])?->id() : null, $stocks);

        return count($pages) === count(array_unique($pages));
    }
}
