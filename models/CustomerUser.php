<?php

use Kirby\Cms\User;

class CustomerUser extends User
{
    public static function phpBlueprint(): array
    {
        // https://getkirby.com/docs/guide/users/permissions
        return [
            'name' => 'customer',
            'title' => 'Kart Customer',
            'icon' => 'cart',
            'permissions' => [
                'access' => [
                    'panel' => false, // lock the user out of the panel
                ],
                'files' => false,
                'languages' => false,
                'pages' => true, // can manipulate pages, like orders
                'site' => false,
                'user' => true, // can update itself (cart, wishlist, ...)
                'users' => false,
            ],
            'fields' => [
                'cart' => [
                    'type' => 'hidden',
                ],
                'wishlist' => [
                    'type' => 'hidden',
                ],
            ],
        ];
    }
}
