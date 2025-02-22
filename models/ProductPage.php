<?php

use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Helper;
use Bnomei\Kart\Router;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
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
                'update' => defined('KART_PRODUCTS_UPDATE') && constant('KART_PRODUCTS_UPDATE') === true,
            ],
            'sections' => [
                'stats' => [
                    'label' => t('bnomei.kart.summary'),
                    'type' => 'stats',
                    'reports' => [
                        [
                            'value' => '{{ page.formattedPrice() }}',
                            'info' => '+ {{ page.formattedTax() }}',
                        ],
                        [
                            'label' => t('bnomei.kart.sold'),
                            'value' => '{{ page.sold }}',
                            'link' => '{{ site.kart.page("orders").url }}',
                        ],
                        [
                            'label' => t('bnomei.kart.stock'),
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
                            'label' => t('bnomei.kart.price'),
                            'type' => 'number',
                            'min' => 0,
                            'step' => 0.01,
                            'default' => 0,
                            'required' => true,
                            'after' => '{{ kirby.option("bnomei.kart.currency") }}',
                            'width' => '1/4',
                        ],
                        'tax' => [
                            'label' => t('bnomei.kart.tax'),
                            'type' => 'number',
                            'min' => 0,
                            'max' => 100,
                            'step' => 0.01,
                            'default' => 0,
                            'required' => true,
                            'after' => '%',
                            'width' => '1/4',
                        ],
                        'gap' => [
                            'type' => 'gap',
                            'width' => '1/2',
                        ],
                        'description' => [
                            'label' => t('bnomei.kart.description'),
                            'type' => 'textarea',
                            'width' => '1/2',
                        ],
                        'gallery' => [
                            'label' => t('bnomei.kart.gallery'),
                            'type' => 'files',
                            'query' => 'page.parent.images',
                            'uploads' => [
                                'parent' => 'page.parent',
                                'template' => 'product-gallery',
                            ],
                            'width' => '1/2',
                        ],
                        '_dump' => [
                            'label' => t('bnomei.kart.raw-values'),
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

    public function withoutStocks(): Pages
    {
        return $this->children()->filterBy(fn (ProductPage $page) => ! is_numeric($page->stock()));
    }

    public function sold(): ?int
    {
        return array_sum(kart()->page(ContentPageEnum::ORDERS)->children()->toArray(
            fn (OrderPage $order) => $order->productsCount($this)
        ));
    }

    public function formattedPrice(): string
    {
        return Helper::formatCurrency($this->price()->toFloat());
    }

    public function formattedSum(): string
    {
        return $this->formattedPrice();
    }

    public function formattedTax(): string
    {
        return Helper::formatCurrency(
            $this->price()->toFloat() *
            $this->tax()->toFloat() / 100.0
        );
    }

    public function formattedSumTax(): string
    {
        return Helper::formatCurrency(
            $this->price()->toFloat() *
            (1.0 + $this->tax()->toFloat() / 100.0)
        );
    }

    public function add(): string
    {
        return Router::cart_add($this);
    }

    public function remove(): string
    {
        return Router::cart_remove($this);
    }

    public function wish(): string
    {
        return Router::wishlist_add($this);
    }

    public function forget(): string
    {
        return Router::wishlist_remove($this);
    }

    public function priceIds(): array
    {
        // TODO: return a list of all know priceIds for this product
        // uses to find of the product was purchased with a given priceId
        return [];
    }

    public static function findByPriceId(string $priceId): ?ProductPage
    {
        return kart()->page(ContentPageEnum::PRODUCTS)->children()
            ->filterBy(fn (ProductPage $p) => in_array($priceId, $p->priceIds()))->first();
    }

    public function updateStock(int $quantity): ?int
    {
        /** @var StockPage $stockPage */
        $stockPage = kart()->page(ContentPageEnum::STOCKS)->stockPages($this->uuid()->toString())->first();
        if ($stockPage) {
            return $stockPage->updateStock($quantity);
        }

        return null;
    }
}
