<?php

use Kirby\Cms\Page;

class OrderPage extends Page
{
    public static function create(array $props): Page
    {
        // enforce unique but short slug with the option to overwrite it in a closure
        $props['slug'] = option('kart.orders.slug', $props['slug']);
        if ($props['slug'] instanceof \Closure) {
            $props['slug'] = $props['slug'](kart()->page('orders'), $props);
            $props['uuid'] = $props['slug'];
        }

        return parent::create($props);
    }

    public static function phpBlueprint(): array
    {
        return [
            'options' => [
                'changeSlug' => false,
                'changeTemplate' => false,
            ],
            'preset' => 'page', // TODO: define fields
        ];
    }
}
