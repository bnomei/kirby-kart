<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Kart;
use Bnomei\Kart\ProductStorage;
use Bnomei\Kart\Router;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Content\Field;
use Kirby\Content\Storage;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

/**
 * @method Field description()
 * @method Field price()
 * @method Field tax()
 * @method Field gallery()
 * @method Field downloads()
 * @method Field raw()
 * @method Field categories()
 * @method Field tags()
 * @method Field maxapo()
 */
class ProductPage extends Page
{
    public static function create(array $props): Page
    {
        $parent = kart()->page(ContentPageEnum::PRODUCTS);

        // enforce unique but short slug with the option to overwrite it in a closure
        $uuid = kart()->option('products.product.uuid');
        if ($uuid instanceof Closure) {
            $uuid = $uuid($parent, $props);
            $props['slug'] = Str::slug($props['content']['title'] ?? $uuid);
            $props['content']['uuid'] = $uuid;
        }

        $props['parent'] = $parent;
        $props['isDraft'] = false;
        $props['template'] = kart()->option('products.product.template', 'product');
        $props['model'] = kart()->option('products.product.model', 'product');

        /** @var ProductPage $p */
        $p = parent::create($props);
        $p = $p->changeStatus('listed');

        return $p;
    }

    public function storage(): Storage
    {
        $this->storage ??= new ProductStorage(model: $this);

        return $this->storage;
    }

    public static function phpBlueprint(): array
    {
        return [
            'name' => 'product',
            'num' => '{{ page.created.toDate("YmdHis") }}',
            'options' => [
                'changeTemplate' => false,
            ],
            'tabs' => [
                'provider' => [
                    'label' => 'bnomei.kart.provider-storage',
                    'icon' => 'globe',
                    'sections' => [
                        'stats' => [
                            'label' => 'bnomei.kart.summary',
                            'type' => 'stats',
                            'reports' => [
                                [
                                    'value' => '{{ page.formattedPrice() }}',
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
                                'description' => [
                                    'label' => 'bnomei.kart.description',
                                    'type' => 'textarea',
                                    // 'virtual' => true,
                                ],
                                'price' => [
                                    'label' => 'bnomei.kart.price',
                                    'type' => 'number',
                                    'min' => 0,
                                    'step' => 0.01,
                                    'default' => 0,
                                    // 'required' => true, // does not work with pruning
                                    'after' => '{{ kirby.option("bnomei.kart.currency") }}',
                                    'width' => '1/2',
                                    'translate' => false,
                                    'virtual' => true,
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
                                'created' => [
                                    'label' => 'bnomei.kart.created',
                                    'type' => 'date',
                                    'time' => true,
                                    'default' => 'now',
                                    'translate' => false,
                                    'width' => '1/2',
                                    // 'virtual' => true, // needed for `num`
                                ],
                                'categories' => [
                                    'label' => 'bnomei.kart.categories',
                                    'type' => 'tags',
                                    'options' => [
                                        'type' => 'query',
                                        'query' => 'page.siblings.pluck("categories", ",", true)',
                                    ],
                                    'width' => '1/2',
                                    'translate' => false,
                                    // 'virtual' => true,
                                ],
                                'tags' => [
                                    'label' => 'bnomei.kart.tags',
                                    'type' => 'tags',
                                    'options' => [
                                        'type' => 'query',
                                        'query' => 'page.siblings.pluck("tags", ",", true)',
                                    ],
                                    'width' => '1/2',
                                    'translate' => false,
                                    // 'virtual' => true,
                                ],
                                'gallery' => [
                                    'label' => 'bnomei.kart.gallery',
                                    'type' => 'files',
                                    'query' => 'page.parent.images',
                                    'uploads' => [
                                        'parent' => 'page.parent',
                                        // 'template' => 'product-gallery',
                                    ],
                                    'width' => '1/2',
                                    'translate' => false,
                                    // 'virtual' => true,
                                ],
                                'downloads' => [
                                    'label' => 'bnomei.kart.downloads',
                                    'type' => 'files',
                                    'query' => 'page.parent.files',
                                    'uploads' => [
                                        'parent' => 'page.parent',
                                        // 'template' => 'product-downloads',
                                    ],
                                    'width' => '1/2',
                                    'translate' => false,
                                    // 'virtual' => true,
                                ],
                                'maxapo' => [
                                    'label' => 'bnomei.kart.max-amount-per-order',
                                    'type' => 'number',
                                    // 'min' => 0, // allow stock to be negative when updating from orders
                                    'step' => 1,
                                    'translate' => false,
                                    'width' => '1/4',
                                    'help' => '{{ site.kart.option("orders.order.maxapo") }}',
                                ],
                                'raw' => [
                                    'type' => 'hidden',
                                    'translate' => false,
                                    'virtual' => true,
                                ],
                                '_dump' => [
                                    'label' => 'bnomei.kart.raw-values',
                                    'type' => 'info',
                                    'theme' => 'info',
                                    'text' => '{< page.dump("raw", 82) >}',
                                ],
                            ],
                        ],
                    ],
                ],
                'local' => [
                    'label' => 'bnomei.kart.local-storage',
                    'icon' => 'server',
                    'extends' => 'tabs/product',
                ],
            ],
        ];
    }

    /**
     * @kql-allowed
     */
    public function inStock(): bool
    {
        $stock = $this->stock();
        if (is_string($stock)) {
            return true;
        }

        return is_numeric($stock) && $stock > 0;
    }

    /**
     * @kql-allowed
     */
    public function stock(bool $withHold = false): int|string
    {
        /** @var StocksPage $stocks */
        $stocks = kart()->page(ContentPageEnum::STOCKS);

        return $stocks->stock($this->uuid()->toString(), $withHold) ?? '∞';
    }

    public function stockUrl(): ?string
    {
        return kart()->stocks()
            ->filterBy(fn ($page) => $page->page()->toPage()?->uuid()->id() === $this->uuid()->id())
            ->first()?->panel()->url() ?? kart()->page(ContentPageEnum::STOCKS)->panel()->url();
    }

    /**
     * @kql-allowed
     */
    public function sold(): ?int
    {
        return array_sum(kart()->orders()->toArray(
            fn (OrderPage $order) => $order->productsCount($this)
        ));
    }

    /**
     * @kql-allowed
     */
    public function formattedPrice(): string
    {
        return Kart::formatCurrency($this->price()->toFloat());
    }

    /**
     * @kql-allowed
     */
    public function add(): string
    {
        return Router::cart_add($this);
    }

    public function addToCart(): string
    {
        return $this->add();
    }

    /**
     * @kql-allowed
     */
    public function buy(): string
    {
        return Router::cart_buy($this);
    }

    public function buyNow(): string
    {
        return $this->buy();
    }

    /**
     * @kql-allowed
     */
    public function remove(): string
    {
        return Router::cart_remove($this);
    }

    public function removeFromCart(): string
    {
        return $this->remove();
    }

    /**
     * @kql-allowed
     */
    public function later(): string
    {
        return Router::cart_later($this);
    }

    public function moveFromCartToWishlist(): string
    {
        return $this->later();
    }

    /**
     * @kql-allowed
     */
    public function wish(): string
    {
        return Router::wishlist_add($this);
    }

    public function addToWishlist(): string
    {
        return $this->wish();
    }

    /**
     * @kql-allowed
     */
    public function forget(): string
    {
        return Router::wishlist_remove($this);
    }

    public function removeFromWishlist(): string
    {
        return $this->forget();
    }

    /**
     * @kql-allowed
     */
    public function now(): string
    {
        return Router::wishlist_now($this);
    }

    public function moveFromWishlistToCart(): string
    {
        return $this->now();
    }

    /**
     * @todo Not implemented
     */
    public function priceIds(): array
    {
        // return a list of all know priceIds for this product
        // uses to find of the product was purchased with a given priceId
        return [];
    }

    /**
     * @kql-allowed
     */
    public function firstGalleryImage(): ?File
    {
        return $this->gallery()->toFile();
    }

    /**
     * @kql-allowed
     */
    public function firstGalleryImageUrl(): ?string
    {
        return $this->firstGalleryImage()?->resize(1920)->url();
    }

    /**
     * @kql-allowed
     */
    public function maxAmountPerOrder(): ?int
    {
        return $this->maxapo()->isEmpty() ? intval(kart()->option('orders.order.maxapo')) : $this->maxapo()->toInt();
    }
}
