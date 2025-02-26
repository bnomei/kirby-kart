<?php

use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Helper;
use Bnomei\Kart\Router;
use Kirby\Cms\Page;
use Kirby\Content\Field;
use Kirby\Toolkit\A;

/**
 * @method Field description()
 * @method Field price()
 * @method Field tax()
 */
class ProductPage extends Page
{
    public static function create(array $props): Page
    {
        // enforce unique but short slug with the option to overwrite it in a closure
        $uuid = kirby()->option('bnomei.kart.products.product.uuid');
        if ($uuid instanceof Closure) {
            $uuid = $uuid(kart()->page(ContentPageEnum::PRODUCTS), $props);
            $props['content']['uuid'] = $uuid;
        }

        $props['template'] = kirby()->option('bnomei.kart.products.product.template', 'product');
        $props['model'] = kirby()->option('bnomei.kart.products.product.model', 'product');

        return parent::create($props);
    }

    public static function phpBlueprint(): array
    {
        return [
            'name' => 'product',
            'options' => [
                'changeTemplate' => false,
                'update' => ! defined('KART_PRODUCTS_UPDATE') || constant('KART_PRODUCTS_UPDATE') === false,
            ],
            'sections' => [
                'stats' => [
                    'label' => 'bnomei.kart.summary',
                    'type' => 'stats',
                    'reports' => [
                        [
                            'value' => '{{ page.formattedPrice() }}',
                            'info' => '+ {{ page.formattedTax() }}',
                        ],
                        [
                            'label' => 'bnomei.kart.sold',
                            'value' => '{{ page.sold }}',
                            'link' => '{{ site.kart.page("orders").url }}',
                        ],
                        [
                            'label' => 'bnomei.kart.stock',
                            'value' => '{{ page.stock }}',
                            'link' => '{{ page.stockUrl }}',
                        ],
                    ],
                ],
                'meta' => [
                    'type' => 'fields',
                    'fields' => [
                        'line' => [
                            'type' => 'line',
                        ],
                        'price' => [
                            'label' => 'bnomei.kart.price',
                            'type' => 'number',
                            'min' => 0,
                            'step' => 0.01,
                            'default' => 0,
                            'required' => true,
                            'after' => '{{ kirby.option("bnomei.kart.currency") }}',
                            'width' => '1/2',
                        ],
                        /* tax and taxrate are better of handles by the checkout flow
                        'taxrate' => [
                            'label' => 'bnomei.kart.taxrate',
                            'type' => 'number',
                            'min' => 0,
                            'max' => 100,
                            'step' => 0.01,
                            'default' => 0,
                            'required' => true,
                            'after' => '%',
                            'width' => '1/4',
                        ],
                        */
                        'gap' => [
                            'type' => 'gap',
                            'width' => '1/2',
                        ],
                        'description' => [
                            'label' => 'bnomei.kart.description',
                            'type' => 'textarea',
                            'width' => '1/2',
                        ],
                        'gallery' => [
                            'label' => 'bnomei.kart.gallery',
                            'type' => 'files',
                            'query' => 'page.parent.images',
                            'uploads' => [
                                'parent' => 'page.parent',
                                'template' => 'product-gallery',
                            ],
                            'width' => '1/2',
                        ],
                        '_dump' => [
                            'label' => 'bnomei.kart.raw-value',
                            'type' => 'info',
                            'theme' => 'info',
                            'text' => '{< page.dump("raw", 82) >}',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function stock(): int|string
    {
        /** @var StocksPage $stocks */
        $stocks = kart()->page(ContentPageEnum::STOCKS);

        return $stocks->stock($this->uuid()->toString()) ?? '?';
    }

    public function stockUrl(): ?string
    {
        /** @var StocksPage $stocks */
        $stocks = kart()->page(ContentPageEnum::STOCKS);

        return $stocks
            ->children()
            ->filterBy(fn ($page) => $page->page()->toPage()?->uuid()->id() === $this->uuid()->id())
            ->first()?->panel()->url() ?? $stocks->panel()->url();
    }

    /**
     * @kql-allowed
     */
    public function sold(): ?int
    {
        return array_sum(kart()->page(ContentPageEnum::ORDERS)->children()->toArray(
            fn (OrderPage $order) => $order->productsCount($this)
        ));
    }

    /**
     * @kql-allowed
     */
    public function formattedPrice(): string
    {
        return Helper::formatCurrency($this->price()->toFloat());
    }

    /**
     * @kql-allowed
     */
    public function add(): string
    {
        return Router::cart_add($this);
    }

    /**
     * @kql-allowed
     */
    public function remove(): string
    {
        return Router::cart_remove($this);
    }

    /**
     * @kql-allowed
     */
    public function wish(): string
    {
        return Router::wishlist_add($this);
    }

    /**
     * @kql-allowed
     */
    public function forget(): string
    {
        return Router::wishlist_remove($this);
    }

    /**
     * @todo Not implemented
     */
    public function priceIds(): array
    {
        // TODO: return a list of all know priceIds for this product
        // uses to find of the product was purchased with a given priceId
        return [];
    }
}
