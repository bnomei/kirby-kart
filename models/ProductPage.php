<?php

use Kirby\Cms\Page;

/**
 * @method \Kirby\Content\Field description()
 * @method \Kirby\Content\Field price()
 * @method \Kirby\Content\Field tax()
 * @method \Kirby\Content\Field availability()
 */
class ProductPage extends Page
{
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
                        ],
                        [
                            'label' => t('kart.stock', 'Stock'),
                            'value' => '{{ page.stock }}',
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
                            'text' => '{< page.raw >}',
                            'width' => '1/2',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function raw(): string
    {
        return '<code>'.str_replace([' ', '&nbsp;&nbsp;'], ['&nbsp;', '&nbsp;'], json_encode([$this->content->toArray()], JSON_PRETTY_PRINT)).'</code>';
    }

    public function stock(): int|string
    {
        /** @var \StocksPage $stocks */
        $stocks = kart()->page('stocks');

        return $stocks->stock($this->uuid()->toString()) ?? '?';
    }

    public function sold(): ?int
    {
        return array_sum(kart()->page('orders')->children()->toArray(
            fn (OrderPage $order) => $order->productsCount($this)
        ));
    }

    public function formattedPrice(): string
    {
        return \Bnomei\Kart\Helper::formatCurrency($this->price()->toFloat());
    }

    public function formattedSum(): string
    {
        return $this->formattedPrice();
    }

    public function formattedTax(): string
    {
        return \Bnomei\Kart\Helper::formatCurrency(
            $this->price()->toFloat() *
            $this->tax()->toFloat() / 100.0
        );
    }

    public function formattedSumTax(): string
    {
        return \Bnomei\Kart\Helper::formatCurrency(
            $this->price()->toFloat() *
            (1.0 + $this->tax()->toFloat() / 100.0)
        );
    }
}
