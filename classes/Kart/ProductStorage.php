<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Kirby\Cms\Language;
use Kirby\Content\PlainTextStorage;
use Kirby\Content\VersionId;
use Kirby\Toolkit\A;

class ProductStorage extends PlainTextStorage
{
    public function read(VersionId $versionId, Language $language): array
    {
        // reading the file
        $content = parent::read($versionId, $language);

        // hydrate with provider
        if (kart()->provider()->virtual()) {
            $uuid = kart()->option('products.product.uuid');
            if ($uuid instanceof \Closure === false) {
                throw new \Exception('kart.products.product.uuid must be a closure');
            }
            foreach (kart()->provider()->products() as $product) {
                if (A::get($content, 'uuid', time()) === $uuid(null, $product)) {
                    // provider overwrites local changes
                    $content = A::merge($content, A::get($product, 'content', []));
                    break;
                }
            }
        }

        return $content;
    }

    protected function write(VersionId $versionId, Language $language, array $fields): void
    {
        // remove virtual fields
        if (kart()->provider()->virtual() === 'prune') {
            $virtualFields = array_keys(A::filter(
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
