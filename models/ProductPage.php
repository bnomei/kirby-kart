<?php

use Kirby\Cms\Page;

class ProductPage extends Page
{
    public static function phpBlueprint(): array
    {
        return [
            'options' => [
                'changeTemplate' => false,
            ],
            'preset' => 'page', // TODO: define fields
        ];
    }
}
