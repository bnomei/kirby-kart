<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\Provider;

class Kirby extends Provider
{
    protected string $name = 'kirby';

    public function checkout(): string
    {
        return '';
    }
}
