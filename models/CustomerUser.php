<?php

use Kirby\Cms\Pages;
use Kirby\Cms\User;
use Kirby\Content\Field;

/**
 * @method Field fastspring()
 * @method Field gumroad()
 * @method Field invoice_ninja()
 * @method Field kirby_cms()
 * @method Field lemonsqueeze()
 * @method Field mollie()
 * @method Field paddle()
 * @method Field payone()
 * @method Field paypal()
 * @method Field snipcart()
 * @method Field stripe()
 * @method bool isCustomer()
 * @method string gravatar()
 * @method \Bnomei\Kart\Kart kart()
 * @method bool hasPurchased(ProductPage|string $product)
 * @method bool hasMadePaymentFor(string $provider, ProductPage $productPage)
 * @method Pages<string, OrderPage> orders()
 * @method Pages<string, OrderPage> completedOrders()
 */
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
                'kart_cart' => [
                    'type' => 'hidden',
                ],
                'kart_wishlist' => [
                    'type' => 'hidden',
                ],
            ],
        ];
    }
}
