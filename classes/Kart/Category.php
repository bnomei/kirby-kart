<?php

namespace Bnomei\Kart;

use Kirby\Toolkit\Obj;

/**
 * @method string id()
 * @method string text()
 * @method int count()
 * @method string value()
 * @method bool isActive()
 * @method string url()
 * @method string urlWithParams()
 */
class Category extends Obj
{
    public function __toString(): string
    {
        return $this->text().' ('.$this->count().')';
    }
}
