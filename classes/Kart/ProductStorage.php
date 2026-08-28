<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Closure;
use Exception;
use Kirby\Cms\Language;
use Kirby\Cms\Page;
use Kirby\Content\PlainTextStorage;
use Kirby\Content\VersionId;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

class ProductStorage extends PlainTextStorage
{
    public function read(VersionId $versionId, Language $language): array
    {
        // reading the file
        $content = parent::read($versionId, $language);

        // hydrate with provider
        $provider = kart()->provider();
        $virtual = $provider->virtual();
        if ($virtual !== false) {
            $uuid = kart()->option('products.product.uuid');
            if ($uuid instanceof Closure === false) {
                throw new Exception('kart.products.product.uuid must be a closure');
            }
            $rawProvider = trim(strval(A::get($content, 'raw_provider', '')));
            foreach ($provider->products() as $product) {
                $productId = A::get($product, 'id');
                $matches = $rawProvider === ''
                    ? A::get($content, 'uuid') === $uuid(null, $product) ||
                        $this->matchesProductSlug($product) === true
                    : (is_int($productId) || is_string($productId)) &&
                        $rawProvider === $provider->name().'|'.trim(strval($productId));

                if ($matches) {
                    // provider overwrites local changes for allowed virtual fields
                    $virtualContent = A::get($product, 'content', []);
                    $virtualFields = is_array($virtual) ? $virtual : array_keys(A::filter(
                        $this->model()->blueprint()->fields(),
                        fn ($field) => A::get($field, 'virtual') === true
                    ));
                    $virtualContent = array_intersect_key(
                        $virtualContent,
                        array_flip($virtualFields)
                    );
                    $content = A::merge($content, $virtualContent);
                    break;
                }
            }
        }

        return $content;
    }

    private function matchesProductSlug(array $product): bool
    {
        $model = $this->model();
        if ($model instanceof Page === false) {
            return false;
        }

        $title = A::get($product, 'content.title');
        if (is_scalar($title) && trim(strval($title)) !== '') {
            return Str::slug(strval($title)) === $model->slug();
        }

        $slug = A::get($product, 'slug');
        if (is_scalar($slug) && trim(strval($slug)) !== '') {
            return Str::slug(strval($slug)) === $model->slug();
        }

        return false;
    }

    protected function write(VersionId $versionId, Language $language, array $fields): void
    {
        // remove (all or some) virtual fields
        $virtual = kart()->provider()->virtual();
        if ($virtual !== false) {
            $virtualFields = is_array($virtual) ? $virtual : array_keys(A::filter(
                $this->model()->blueprint()->fields(),
                fn ($field) => A::get($field, 'virtual') === true
            ));
            foreach ($virtualFields as $field) {
                unset($fields[$field]);
            }
        }

        parent::write($versionId, $language, $fields);
    }
}
