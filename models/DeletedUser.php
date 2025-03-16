<?php

use Kirby\Cms\User;

class DeletedUser extends User
{
    public static function phpBlueprint(): array
    {
        // https://getkirby.com/docs/guide/users/permissions
        return [
            'name' => 'deleted',
            'title' => 'Soft Deleted User',
            'icon' => 'cancel',
            'permissions' => [
                'access' => false,
                'files' => false,
                'languages' => false,
                'pages' => false,
                'site' => false,
                'user' => false,
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
