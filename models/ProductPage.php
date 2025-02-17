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
            'fields' => [
                'description' => [
                    'label' => t('kart.product.description', 'Description'),
                    'type' => 'textarea',
                ],
                'price' => [
                    'label' => t('kart.product.price', 'Price'),
                    'type' => 'number',
                    'min' => 0,
                    'step' => 0.01,
                    'default' => 0,
                    'required' => true,
                ],
                'tax' => [
                    'label' => t('kart.product.tax', 'Tax'),
                    'type' => 'number',
                    'min' => 0,
                    'max' => 100,
                    'step' => 0.01,
                    'default' => 0,
                    'required' => true,
                ],
                'availability' => [
                    'label' => t('kart.product.availability', 'Availability'),
                    'type' => 'radio',
                    'options' => [
                        'inStock' => t('kart.product.inStock', 'In Stock'),
                        'outOfStock' => t('kart.product.outOfStock', 'Out of Stock'),
                    ],
                ],
            ],
        ];
    }

    public function stock(): ?int
    {
        $items = kart()->page('stocks')->stocks()->toStructure()
            ->filterBy(fn ($i) => $i->page()->toPage()?->id() === $this->id())
            ->toArray(fn ($i) => intval($i->stock()->toInt()));

        return count($items) ? $items[0] : null;
    }

    public function formattedPrice(): string
    {
        return \Bnomei\Kart\Data::formatCurrency($this->price()->toFloat());
    }

    public function formattedTax(): string
    {
        return \Bnomei\Kart\Data::formatCurrency(
            $this->price()->toFloat() *
            $this->tax()->toFloat() / 100.0
        );
    }

    public function formattedSumTax(): string
    {
        return \Bnomei\Kart\Data::formatCurrency(
            $this->price()->toFloat() *
            (1.0 + $this->tax()->toFloat() / 100.0)
        );
    }
}
