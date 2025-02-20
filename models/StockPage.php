<?php

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
        // enforce unique but short slug with the option to overwrite it in a closure
        $uuid = kirby()->option('bnomei.kart.stocks.stock.uuid');
        if ($uuid instanceof Closure) {
            $uuid = $uuid(kart()->page(\Bnomei\Kart\ContentPageEnum::STOCKS), $props);
            $props['slug'] = Str::slug(str_replace('st_', '', $uuid));
            $props['content']['uuid'] = $uuid;
            $props['content']['title'] = strtoupper($uuid);
        }

        $p = parent::create($props);

        return $p->changeStatus('unlisted');
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
                    'label' => t('kart.product', 'Product'),
                    'type' => 'pages',
                    'query' => 'site.kart.page("products")',
                    // 'required' => true,
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

    public function stockPad(int $length): string
    {
        return str_pad($this->stock()->value(), $length, '0', STR_PAD_LEFT);
    }
}
