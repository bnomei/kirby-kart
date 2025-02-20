<?php

use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Helper;
use Bnomei\Kart\Router;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Content\Field;

/**
 * @method Field description()
 * @method Field price()
 * @method Field tax()
 * @method Field availability()
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

        return parent::create($props);
    }

    public static function phpBlueprint(): array
    {
        return [
            'name' => 'product',
            'options' => [
                'changeTemplate' => false,
            ],
            'sections' => [
                'stats' => [
                    'label' => t('kart.summary', 'Summary'),
                    'type' => 'stats',
                    'reports' => [
                        [
                            'value' => '{{ page.formattedPrice() }}',
                            'info' => '+ {{ page.formattedTax() }}',
                        ],
                        [
                            'label' => t('kart.sold', 'Sold'),
                            'value' => '{{ page.sold }}',
                            'link' => '{{ site.kart.page("orders").url }}',
                        ],
                        [
                            'label' => t('kart.stock', 'Stock'),
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
                            'label' => t('kart.product.price', 'Price'),
                            'type' => 'number',
                            'min' => 0,
                            'step' => 0.01,
                            'default' => 0,
                            'required' => true,
                            'after' => '{{ kirby.option("bnomei.kart.currency") }}',
                            'width' => '1/4',
                        ],
                        'tax' => [
                            'label' => t('kart.product.tax', 'Tax'),
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
                            'label' => t('kart.product.description', 'Description'),
                            'type' => 'textarea',
                            'width' => '1/2',
                        ],
                        'gallery' => [
                            'label' => t('kart.product.gallery', 'Gallery'),
                            'type' => 'files',
                            'query' => 'page.parent.images',
                            'uploads' => [
                                'parent' => 'page.parent',
                                'template' => 'product-gallery',
                            ],
                            'width' => '1/2',
                        ],
                        'raw' => [
                            'label' => t('kart.product.raw', 'Raw'),
                            'type' => 'info',
                            'theme' => 'info',
                            'text' => '{< page.raw(82) >}',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function raw(int $maxWidth = 140): string
    {
        $json = json_encode($this->content->toArray(), JSON_PRETTY_PRINT);
        $lines = explode("\n", $json);
        $wrappedLines = [];

        foreach ($lines as $line) {
            $indentation = strspn($line, ' ');
            $content = trim($line);
            $wrapped = wordwrap($content, $maxWidth - $indentation, "\n", true);
            $wrapped = preg_replace('/^/m', str_repeat(' ', $indentation), $wrapped);
            $wrappedLines[] = $wrapped;
        }

        $json = implode("\n", $wrappedLines);
        $json = str_replace([' ', '&nbsp;&nbsp;'], ['&nbsp;', '&nbsp;'], $json);

        return '<code style="overflow: hidden; width: 100%; display: block">'.$json.'</code>';
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
}
