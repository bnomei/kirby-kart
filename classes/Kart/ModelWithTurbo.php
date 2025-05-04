<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart/Kirby Turbo and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Kirby\Cms\File;
use Kirby\Content\Storage;

trait ModelWithTurbo
{
    /*
     * Helper to flag if trait was applied to a class.
     * Use with `method_exits($obj, 'hasTurbo')` or
     * in calling `$obj->hasTurbo() === true`
     */
    public function hasTurbo(): bool
    {
        // files have turbo if their parents do
        if ($this instanceof File && method_exists($this->parent(), 'hasTurbo')) {
            return $this->parent()?->hasTurbo() === true;
        }

        return true;
    }

    public function inventory(): array
    {
        $turbo = '\\Bnomei\\Turbo';

        if (class_exists($turbo)) {
            return $turbo::singleton()->inventory($this->root()) ?? parent::inventory();
        }

        return parent::inventory();
    }

    public function storage(): Storage
    {
        $turboStorage = '\\Bnomei\\TurboStorage';

        if (class_exists($turboStorage)) {
            return $this->storage ??= new $turboStorage(model: $this);
        }

        return $this->storage ??= parent::storage();
    }
}
