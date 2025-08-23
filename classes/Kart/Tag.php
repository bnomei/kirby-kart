<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Kirby\Toolkit\Obj;
use Stringable;

/**
 * Kart Product Tag
 *
 * @method string id()
 * @method string text()
 * @method int count()
 * @method string value()
 * @method bool isActive()
 * @method string url()
 * @method string urlWithParams()
 */
class Tag extends Obj implements Stringable
{
    public function __toString(): string
    {
        return $this->text().' ('.$this->count().')';
    }
}
