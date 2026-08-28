<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart\Models;

use Bnomei\Kart\Provider;
use InvalidArgumentException;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Content\Field;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

class ProductsPage extends Page
{
    public static function phpBlueprint(): array
    {
        return [
            'name' => 'products',
            'image' => [
                'back' => 'var(--color-black)',
                'color' => 'var(--color-gray-500)',
                'cover' => true,
                'icon' => 'kart-store',
                'query' => false,
            ],
            'options' => [
                'changeSlug' => false,
                'changeTemplate' => false,
                'delete' => false,
                'duplicate' => false,
                'move' => false,
            ],
            'buttons' => [
                'preview' => true,
                'sync' => [
                    'icon' => 'refresh',
                    'text' => 'bnomei.kart.sync-provider',
                    'link' => '{< site.kart.urls.sync() >}',
                ],
                'status' => true,
            ],
            'tabs' => [
                'overview' => [
                    'sections' => [
                        'stats' => [
                            'label' => 'bnomei.kart.summary',
                            'type' => 'stats',
                            'reports' => [
                                [
                                    'label' => 'bnomei.kart.products',
                                    'value' => '{{ page.children.count }}',
                                ],
                                [
                                    'label' => 'bnomei.kart.out-of-stock',
                                    'value' => '{{ page.outOfStock.count }}',
                                    'link' => '{{ site.kart.page("stocks")?.panel.url }}',
                                ],
                                [
                                    'label' => 'bnomei.kart.provider',
                                    'value' => '{{ site.kart.provider.title }}',
                                ],
                                [
                                    'label' => 'bnomei.kart.last-sync',
                                    'value' => '{{ site.kart.provider.updatedAt("products") }}',
                                ],
                            ],
                        ],
                        'meta' => [
                            'type' => 'fields',
                            'fields' => [
                                'categories' => [
                                    'label' => '{{ t("bnomei.kart.categories") }} ({{ site.kart.categories.count }})',
                                    'type' => 'allcategories',
                                    'disabled' => true,
                                    'translate' => false,
                                    'width' => '1/4',
                                ],
                                'tags' => [
                                    'label' => '{{ t("bnomei.kart.tags") }} ({{ site.kart.tags.count }})',
                                    'type' => 'alltags',
                                    'disabled' => true,
                                    'translate' => false,
                                    'width' => '1/2',
                                ],
                                'variants' => [
                                    'label' => '{{ t("bnomei.kart.variants") }} ({{ site.kart.variants.count }})',
                                    'type' => 'allvariants',
                                    'disabled' => true,
                                    'translate' => false,
                                    'width' => '1/4',
                                ],
                                'line' => [
                                    'type' => 'line',
                                ],
                            ],
                        ],
                        'products' => [
                            'label' => 'bnomei.kart.products',
                            'type' => 'pages',
                            'layout' => 'cards',
                            'search' => true,
                            'template' => 'product', // maps to ProductPage model
                            'info' => '{{ page.formattedPrice }} [{{ page.stockWithVariants }}]{{ page.inStock ? "" : " ⚠️" }}{{ page.featured.ecco(" ★") }}{{ page.variants.ecco(" ❖") }}{{ page.downloads.ecco(" ↓") }}',
                            'image' => [
                                'cover' => true,
                                'query' => 'page.gallery.first.toFile',
                            ],
                            'limit' => 1000,
                            'sortable' => false,
                            'sortBy' => 'title asc',
                        ],
                    ],
                ],
                'files' => [
                    'sections' => [
                        'files' => [
                            'type' => 'files',
                            'info' => '{{ file.dimensions }} ・ {{ file.niceSize }}',
                            'layout' => 'cards',
                            'image' => [
                                'cover' => true,
                            ],
                            'limit' => 1000,
                            'search' => true,
                            'sortable' => false,
                            'sortBy' => 'filename asc',
                        ],
                    ],
                ],
            ],

        ];
    }

    public function children(): Pages
    {
        if ($this->children instanceof Pages) {
            return $this->children;
        }

        $this->children = parent::children();
        $provider = kart()->provider();
        $this->materializeProviderProducts($provider, $provider->products());
        assert($this->children instanceof Pages);

        return $this->children;
    }

    public function materializeProviderProducts(Provider $provider, array $products): void
    {
        [$updates, $creates] = $this->materializationPlan($provider, $products);
        $this->applyMaterializationPlan($updates, $creates);
    }

    public function syncProviderProducts(
        Provider $provider,
        array $current,
        array $visible,
        array $previous = [],
    ): void {
        $currentRawProviders = $this->providerProducts($provider, $current);
        $adoptableRawProviders = array_diff_key(
            $currentRawProviders + $this->providerProducts($provider, $previous),
            $this->providerProducts($provider, $visible),
        );
        $policy = $this->missingProductPolicy();
        [$updates, $creates] = $this->materializationPlan($provider, $visible);
        [$backfillUpdates] = $this->materializationPlan(
            $provider,
            array_values($adoptableRawProviders),
            false,
        );

        $updatesByPage = [];
        foreach (array_merge($backfillUpdates, $updates) as [$page, $rawProvider]) {
            $pageId = $page->id();
            if (isset($updatesByPage[$pageId]) && $updatesByPage[$pageId][1] !== $rawProvider) {
                throw new InvalidArgumentException('Multiple provider products resolve to page '.$pageId);
            }
            $updatesByPage[$pageId] = [$page, $rawProvider];
        }

        $this->applyMaterializationPlan(array_values($updatesByPage), $creates);
        $this->reconcileProviderProducts($provider, $currentRawProviders, $policy);
    }

    private function materializationPlan(
        Provider $provider,
        array $products,
        bool $createMissing = true,
    ): array {
        $products = $this->providerProducts($provider, $products);
        $this->children ??= parent::children();
        $pages = $this->childrenAndDrafts();
        $pagesByRawProvider = [];
        $pagesByUuid = [];
        $pagesBySlug = [];

        foreach ($pages as $page) {
            $rawProvider = $page->raw_provider()->value();
            if (is_string($rawProvider) && $rawProvider !== '') {
                if (isset($pagesByRawProvider[$rawProvider])) {
                    throw new InvalidArgumentException('Multiple product pages use raw_provider '.$rawProvider);
                }
                $pagesByRawProvider[$rawProvider] = $page;
            }
            $pagesByUuid[$page->uuid()->id()] = $page;
            $pagesBySlug[$page->slug()] = $page;
        }

        $uuid = kart()->option('products.product.uuid');
        $claimedPages = [];
        $plannedSlugs = [];
        $updates = [];
        $creates = [];

        foreach ($products as $rawProvider => $product) {
            $productUuid = is_callable($uuid) ? $uuid($this, $product) : null;
            $productSlug = $this->productSlug($product, is_string($productUuid) ? $productUuid : null);
            $page = $pagesByRawProvider[$rawProvider] ?? null;

            if (! $page instanceof Page && is_string($productUuid)) {
                $candidate = $pagesByUuid[$productUuid] ?? null;
                if ($candidate instanceof Page) {
                    $candidateRawProvider = $candidate->raw_provider()->value();
                    if (is_string($candidateRawProvider) && $candidateRawProvider !== '') {
                        throw new InvalidArgumentException(
                            'Product UUID '.$productUuid.' belongs to '.$candidateRawProvider.', not '.$rawProvider
                        );
                    }
                    $page = $candidate;
                }
            }

            if ($page instanceof Page) {
                if (isset($claimedPages[$page->id()])) {
                    throw new InvalidArgumentException('Multiple provider products resolve to page '.$page->id());
                }
                $claimedPages[$page->id()] = true;

                if ($page->raw_provider()->value() !== $rawProvider) {
                    $updates[] = [$page, $rawProvider];
                }

                continue;
            }

            if ($createMissing === false) {
                continue;
            }

            if ($productSlug !== '' && isset($pagesBySlug[$productSlug])) {
                throw new InvalidArgumentException(
                    'Provider product '.$rawProvider.' conflicts with existing slug '.$productSlug
                );
            }
            if ($productSlug !== '' && isset($plannedSlugs[$productSlug])) {
                throw new InvalidArgumentException('Multiple provider products resolve to slug '.$productSlug);
            }
            $plannedSlugs[$productSlug] = true;

            $content = A::get($product, 'content');
            if (! is_array($content)) {
                throw new InvalidArgumentException('Provider product '.$rawProvider.' has invalid content');
            }
            $product['content']['raw_provider'] = $rawProvider;
            $creates[] = $product;
        }

        return [$updates, $creates];
    }

    private function applyMaterializationPlan(array $updates, array $creates): void
    {
        $this->kirby()->impersonate('kirby', function () use ($updates, $creates): void {
            foreach ($updates as [$page, $rawProvider]) {
                $page->update(['raw_provider' => $rawProvider]);
            }
            foreach ($creates as $product) {
                $this->createChild($product);
            }
        });
    }

    private function reconcileProviderProducts(Provider $provider, array $currentRawProviders, string $policy): void
    {
        if ($policy === 'keep') {
            return;
        }

        $providerPrefix = $provider->name().'|';
        $this->kirby()->impersonate('kirby', function () use ($policy, $currentRawProviders, $providerPrefix): void {
            foreach ($this->childrenAndDrafts() as $page) {
                $rawProvider = $page->raw_provider()->value();
                if (
                    ! is_string($rawProvider) ||
                    ! str_starts_with($rawProvider, $providerPrefix) ||
                    array_key_exists($rawProvider, $currentRawProviders)
                ) {
                    continue;
                }

                if ($policy === 'delete') {
                    $page->delete(true);
                } elseif ($page->isDraft() === false) {
                    $page->changeStatus('draft');
                }
            }
        });
    }

    private function missingProductPolicy(): string
    {
        $policy = kart()->option('products.product.missing', 'draft');
        if (! is_string($policy) || ! in_array($policy, ['keep', 'draft', 'delete'], true)) {
            throw new InvalidArgumentException('kart.products.product.missing must be keep, draft or delete');
        }

        return $policy;
    }

    private function providerProducts(Provider $provider, array $products): array
    {
        $indexed = [];
        foreach ($products as $product) {
            $id = A::get($product, 'id');
            if (! is_int($id) && ! is_string($id)) {
                throw new InvalidArgumentException('Provider product is missing an id');
            }
            $id = trim(strval($id));
            if ($id === '') {
                throw new InvalidArgumentException('Provider product is missing an id');
            }

            $rawProvider = $provider->name().'|'.$id;
            if (isset($indexed[$rawProvider])) {
                throw new InvalidArgumentException('Duplicate provider product '.$rawProvider);
            }
            $indexed[$rawProvider] = $product;
        }

        return $indexed;
    }

    protected function productSlug(array $product, ?string $fallback = null): string
    {
        $title = A::get($product, 'content.title', $fallback);
        if (is_scalar($title) && trim(strval($title)) !== '') {
            return Str::slug(strval($title));
        }

        return '';
    }

    public function categories(): Field
    {
        return new Field($this, 'categories', implode(',', kart()->allCategories()));
    }

    public function tags(): Field
    {
        return new Field($this, 'tags', implode(',', kart()->allTags()));
    }

    public function variants(): Field
    {
        return new Field($this, 'variants', implode(',', kart()->allVariants()));
    }

    public function outOfStock(): Pages
    {
        return $this->children()->filterBy(function (ProductPage $p) {
            $stock = $p->stockWithVariants();

            return ! (is_string($stock) || $stock > 0);
        });
    }
}
