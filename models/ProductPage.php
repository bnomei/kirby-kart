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
use Bnomei\Kart\Kerbs;
use Bnomei\Kart\ProductStorage;
use Bnomei\Kart\Router;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\StructureObject;
use Kirby\Content\Field;
use Kirby\Content\Storage;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

/**
 * @method Field categories()
 * @method Field description()
 * @method Field details()
 * @method Field downloads()
 * @method Field featured()
 * @method Field gallery()
 * @method Field maxapo()
 * @method Field price()
 * @method Field raw()
 * @method Field rrprice()
 * @method Field tags()
 * @method Field tax()
 * @method Field variants()
 */
class ProductPage extends Page implements Kerbs
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
        // $props['isDraft'] = false;
        $props['template'] = kart()->option('products.product.template', 'product');
        $props['model'] = kart()->option('products.product.model', 'product');

        /** @var ProductPage $p */
        $p = parent::create($props);
        $p = $p->changeStatus('listed');

        return $p;
    }

    public static function phpBlueprint(): array
    {
        return [
            'name' => 'product',
            'num' => '{{ page.created.toDate("YmdHis") }}',
            'image' => [
                'icon' => 'blank',
                'cover' => true,
                'query' => 'page.gallery.toFiles.first',
            ],
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
                                    'label' => 'bnomei.kart.sales-count',
                                    'value' => '{{ page.salesCount }}',
                                    'link' => '{{ site.kart.page("orders")?.panel.url }}',
                                ],
                                [
                                    'label' => 'bnomei.kart.stock',
                                    'value' => '{{ page.stockWithVariants }}',
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
                                'details' => [
                                    'label' => 'bnomei.kart.details',
                                    'type' => 'structure',
                                    'virtual' => true,
                                    'fields' => [
                                        'summary' => [
                                            'label' => 'bnomei.kart.details.summary',
                                            'type' => 'text',
                                        ],
                                        'text' => [
                                            'label' => 'bnomei.kart.details.text',
                                            'type' => 'textarea',
                                        ],
                                        'open' => [
                                            'label' => 'bnomei.kart.details.open',
                                            'type' => 'toggle',
                                            'default' => false,
                                        ],
                                    ],
                                    'columns' => [
                                        'summary' => ['width' => '1/5'],
                                        'text' => ['width' => '3/5'],
                                        'open' => ['width' => '1/5'],
                                    ],
                                ],
                                'price' => [
                                    'label' => 'bnomei.kart.price',
                                    'type' => 'number',
                                    'min' => 0,
                                    'step' => 0.01,
                                    'default' => 0,
                                    // 'required' => true, // does not work with pruning
                                    'after' => '{{ kirby.option("bnomei.kart.currency") }}',
                                    'width' => '1/4',
                                    'translate' => false,
                                    'virtual' => true,
                                ],
                                'rrprice' => [
                                    'label' => 'bnomei.kart.rrprice',
                                    'type' => 'number',
                                    'min' => 0,
                                    'step' => 0.01,
                                    'placeholder' => '{{ page.price }}',
                                    // 'default' => 0,
                                    // 'required' => true, // does not work with pruning
                                    'after' => '{{ kirby.option("bnomei.kart.currency") }}',
                                    'width' => '1/4',
                                    'translate' => false,
                                    'virtual' => false,
                                ],
                                /* tax and taxrate are handled by the checkout flow
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
                                'maxapo' => [
                                    'label' => 'bnomei.kart.max-amount-per-order',
                                    'type' => 'number',
                                    // 'min' => 0, // allow stock to be negative when updating from orders
                                    'step' => 1,
                                    'translate' => false,
                                    'width' => '1/4',
                                    'placeholder' => '{{ site.kart.option("orders.order.maxapo") }}',
                                ],
                                'created' => [
                                    'label' => 'bnomei.kart.created',
                                    'type' => 'date',
                                    'time' => true,
                                    'default' => 'now',
                                    'translate' => false,
                                    'width' => '1/4',
                                    // 'virtual' => true, // needed for `num`
                                ],
                                'categories' => [
                                    'label' => 'bnomei.kart.categories',
                                    'type' => 'tags',
                                    'options' => [
                                        'type' => 'query',
                                        'query' => 'page.siblings.pluck("categories", ",", true)',
                                    ],
                                    'width' => '1/3',
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
                                    'width' => '1/3',
                                    'translate' => false,
                                    // 'virtual' => true,
                                ],
                                'featured' => [
                                    'label' => '★ {{ t("bnomei.kart.featured") }}',
                                    'type' => 'toggle',
                                    'default' => false,
                                    'width' => '1/3',
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
                                    'width' => '1/3',
                                    'translate' => false,
                                    // 'virtual' => true,
                                ],
                                'downloads' => [
                                    'label' => '↓ {{ t("bnomei.kart.downloads") }}',
                                    'type' => 'files',
                                    'query' => 'page.parent.files',
                                    'uploads' => [
                                        'parent' => 'page.parent',
                                        // 'template' => 'product-downloads',
                                    ],
                                    'width' => '1/3',
                                    'translate' => false,
                                    // 'virtual' => true,
                                ],
                                'variants' => [
                                    'label' => '❖ {{ t("bnomei.kart.variants") }}',
                                    'type' => 'structure',
                                    'translate' => false,
                                    'virtual' => true,
                                    'width' => '1/3',
                                    'fields' => [
                                        'price_id' => [
                                            'type' => 'hidden',
                                        ],
                                        'variant' => [
                                            'label' => 'bnomei.kart.variant',
                                            'type' => 'tags',
                                        ],
                                        'price' => [
                                            'label' => 'bnomei.kart.price',
                                            'type' => 'number',
                                            'min' => 0,
                                            'step' => 0.01,
                                            // 'default' => 0, // leave blank instead which will use products price
                                            'after' => '{{ kirby.option("bnomei.kart.currency") }}',
                                        ],
                                        'image' => [
                                            'label' => 'field.blocks.image.name',
                                            'type' => 'files',
                                            'query' => 'page.parent.files',
                                            'uploads' => [
                                                'parent' => 'page.parent',
                                                // 'template' => 'product-downloads',
                                            ],
                                        ],
                                    ],
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
                    'extends' => 'tabs/product-local',
                ],
            ],
        ];
    }

    public function storage(): Storage
    {
        $this->storage ??= new ProductStorage(model: $this);

        return $this->storage;
    }

    /**
     * @kql-allowed
     */
    public function inStock(): bool
    {
        $stock = $this->stockWithVariants();
        if (is_string($stock)) {
            return true;
        }

        return $stock > 0;
    }

    /**
     * @kql-allowed
     */
    public function stockWithVariants(): int|string
    {
        $self = $this->stock();
        $variants = $this->stock(variant: '*');

        if (is_string($self) || is_string($variants)) {
            return $self;
        }

        return $self + $variants;
    }

    /**
     * @kql-allowed
     */
    public function stock(?string $withHold = null, ?string $variant = null): int|string
    {
        /** @var StocksPage $stocks */
        $stocks = kart()->page(ContentPageEnum::STOCKS);

        return $stocks->stock($this->uuid()->toString(), $withHold, $variant) ?? '∞';
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
    public function salesCount(): ?int
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

    public function addToCart(): string
    {
        return $this->add();
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
    public function setAmountInCart(): string
    {
        return Router::cart_set_amount($this);
    }

    public function buyNow(): string
    {
        return $this->buy();
    }

    /**
     * @kql-allowed
     */
    public function buy(): string
    {
        return Router::cart_buy($this);
    }

    public function removeFromCart(): string
    {
        return $this->remove();
    }

    /**
     * @kql-allowed
     */
    public function remove(): string
    {
        return Router::cart_remove($this);
    }

    public function moveFromCartToWishlist(): string
    {
        return $this->later();
    }

    /**
     * @kql-allowed
     */
    public function later(): string
    {
        return Router::cart_later($this);
    }

    public function addToWishlist(): string
    {
        return $this->wish();
    }

    /**
     * @kql-allowed
     */
    public function wish(): string
    {
        return Router::wishlist_add($this);
    }

    public function removeFromWishlist(): string
    {
        return $this->forget();
    }

    /**
     * @kql-allowed
     */
    public function forget(): string
    {
        return Router::wishlist_remove($this);
    }

    public function moveFromWishlistToCart(): string
    {
        return $this->now();
    }

    /**
     * @kql-allowed
     */
    public function now(): string
    {
        return Router::wishlist_now($this);
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
    public function firstGalleryImage(): ?File
    {
        return $this->gallery()->toFile();
    }

    /**
     * @kql-allowed
     */
    public function maxAmountPerOrder(): ?int
    {
        return $this->maxapo()->isEmpty() ? intval(kart()->option('orders.order.maxapo')) : $this->maxapo()->toInt();
    }

    public function updateStock(int $quantity, bool $set = false, ?string $variant = null): ProductPage
    {
        /** @var StocksPage $stocks */
        $stocks = kart()->page(ContentPageEnum::STOCKS);

        $stocks->updateStock($this, $quantity, $set, $variant);

        return $this;
    }

    /**
     * @kql-allowed
     */
    public function gumroadUrl(): ?string
    {
        return A::get($this->raw()->yaml(), 'short_url');
    }

    /**
     * @kql-allowed
     */
    public function lemonsqueezyUrl(): ?string
    {
        return A::get($this->raw()->yaml(), 'buy_now_url');
    }

    /**
     * @kql-allowed
     */
    public function rrpp(): float
    {
        return $this->rrprice()->isNotEmpty() && $this->rrprice()->toInt() !== 0 ?
            round(($this->rrprice()->toFloat() - $this->price()->toFloat()) / $this->rrprice()->toFloat() * 100) : 0;
    }

    public function variantData(bool $resolveImage = false): array
    {
        return $this->variants()->toStructure()->values(function ($i) use ($resolveImage) {
            $variants = $i->variant()->split();
            sort($variants);
            $variant = implode(',', $variants); // no whitespace

            return array_filter([
                'options' => $variants,
                'price' => $i->price()->isNotEmpty() ? $i->price()->toFloat() : null,
                'formattedPrice' => $i->price()->isNotEmpty() ? $i->price()->toFormattedCurrency() : null,
                'image' => $resolveImage ? $i->image()->toFile()?->toKerbs() : $i->image()->toFile()?->name(),
                'variant' => $variant,
                'inStock' => $this->stock(withHold: true, variant: $variant) !== 0,
            ]);
        });
    }

    /*
     * like A::get($product->variantGroups(), 'size.xl')
     */
    private ?array $variantGroups = null;

    public function variantGroups(): array
    {
        if ($this->variantGroups) {
            return $this->variantGroups;
        }

        $groups = [];
        foreach ($this->variants()->toStructure() as $item) {

            $tags = $item->variant()->split();
            sort($tags);
            foreach ($tags as $tag) {
                if (! is_string($tag)) {
                    continue;
                }
                $s = ':';
                if (str_contains($tag, $s) === false) {
                    $s = '.';
                }
                if (str_contains($tag, $s) === false) {
                    $s = '=';
                }
                $kv = explode($s, trim($tag));
                if (count($kv) === 2) {
                    $key = trim($kv[0]);
                    $var = trim($kv[1]);
                    $groups[$key][$var] = t($key.'.'.$var, str_replace('-', ' ', $var));
                }
            }
        }

        foreach (array_keys($groups) as $key) {
            $groups[$key] = array_unique($groups[$key]);
            asort($groups[$key]);
        }

        $this->variantGroups = $groups;

        return $this->variantGroups;
    }

    public function hasVariant(?string $variant = null): bool
    {
        if (empty($variant)) {
            return false;
        }

        // using the parsed groups to allow alternative spellings like: x:1,x=1,x.1
        // all tags within the variant must be present for a match to succeed
        $tags = explode(',', $variant);
        foreach ($tags as $tag) {
            if (! A::get($this->variantGroups(), str_replace([':', '='], ['.', '.'], $tag))) {
                return false;
            }
        }

        return true;
    }

    public function variantFromRequestData(array $data = []): ?string
    {
        // resolve a variant string to validatable parts
        if ($va = A::get($data, 'variant')) {
            if (is_string($va)) {
                foreach (explode(',', urldecode($va)) as $tag) {
                    $s = ':';
                    if (! str_contains($tag, $s)) {
                        $s = '=';
                    }
                    if (! str_contains($tag, $s)) {
                        $s = '.';
                    }
                    $t = explode($s, $tag);
                    if (count($t) === 2) {
                        $data[trim($t[0])] = trim($t[1]);
                    }
                }
            }
        }

        $data = Kart::sanitize($data);
        $variant = [];
        foreach (array_keys($this->variantGroups()) as $key) {
            $value = A::get($data, $key);
            if ($value && is_string($value)) {
                $variant[] = $key.':'.trim($value);
            }
        }
        sort($variant);

        return empty($variant) ? null : implode(',', $variant);
    }

    public function priceWithVariant(?string $variant = null): float
    {
        foreach ($this->variantData(false) as $v) {
            if ($v['variant'] === $variant) {
                if ($price = A::get($v, 'price')) {
                    return floatval($price);
                }
            }
        }

        return $this->price()->toFloat();
    }

    public function ownedByUser(?\Kirby\Cms\User $user = null): bool
    {
        $user ??= kirby()->user();
        if ($user === null) {
            return false;
        }

        return $user->hasPurchased($this);
    }

    protected array $kerbs = [];
    public function toKerbs(bool $full = true): array
    {
        if ($kerbs = A::get($this->kerbs, $full ? 'full' : 'partial')) {
            return $kerbs;
        }

        $this->kerbs['partial'] = [
            'add' => $this->add(),
            'blocks' => $this->blocks()->isEmpty() ? null : $this->blocks()->toKerbs('blocks'),
            'buy' => $this->buy(),
            'categories' => $this->categories()->split(),
            'description' => $this->description()->kti()->value(),
            'featured' => $this->featured()->toBool(),
            'firstGalleryImage' => $this->firstGalleryImage()?->toKerbs(),
            'forget' => $this->forget(),
            'formattedPrice' => $this->formattedPrice(),
            'formattedRRPrice' => $this->rrprice()->isNotEmpty() ? $this->rrprice()->toFormattedCurrency() : null,
            'id' => $this->id(),
            'inStock' => $this->stock(withHold: true) !== 0,
            'later' => $this->later(),
            'layouts' => $this->layout()->or($this->layouts())->toKerbs('layouts'),
            'now' => $this->now(),
            'price' => $this->price()->toFloat(),
            'remove' => $this->remove(),
            'rrpp' => $this->rrpp(),
            'rrprice' => $this->rrprice()->isNotEmpty() ? $this->rrprice()->toFloat() : null,
            'setAmountInCart' => $this->setAmountInCart(),
            // 'stock' => $this->stock(withHold: true),
            'tags' => $this->tags()->split(),
            'title' => $this->title()->value(),
            'url' => $this->url(),
            'variants' => $this->variantData(true),
            'variantGroups' => $this->variantGroups(),
            // 'uuid' => $this->uuid()->id(),
            'wish' => $this->wish(),
        ];

        $this->kerbs['full'] = array_merge($this->kerbs['partial'], [
            'details' => $full ? $this->details()->toStructure()->values(function (StructureObject $i) {
                $s = $i->summary()->value() ?? '';

                return [
                    'summary' => ! empty($s) ? Kart::query(t($s, $s), $this) : '',
                    'text' => Kart::query($i->text()->kt()->value(), $this),
                    'open' => $i->open()->toBool(),
                ];
            }) : null,
            'gallery' => $full ? $this->gallery()->toFiles()->values(fn (File $f) => $f->toKerbs()) : null,
            'related' => $full ? kart()->productsRelated($this)->not($this)->values(fn (ProductPage $p) => $p->id()) : null,
        ]);

        return $this->kerbs[$full ? 'full' : 'partial'];
    }
}
