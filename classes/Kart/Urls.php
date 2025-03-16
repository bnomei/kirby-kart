<?php

namespace Bnomei\Kart;

use Kirby\Cms\Page;
use ProductPage;

/**
 * @method string captcha()
 * @method string cart()
 * @method string csrf()
 * @method string kart()
 * @method string account_delete()
 * @method string logout()
 * @method string login(?string $email = null)
 * @method string login_magic(?string $email = null)
 * @method string magiclink(?string $email = null)
 * @method string signup_magic(?string $email = null)
 * @method string sync(Page|null|string $page)
 * @method string cart_add(ProductPage $product)
 * @method string cart_remove(ProductPage $product)
 * @method string cart_later(ProductPage $product)
 * @method string cart_buy(ProductPage $product)
 * @method string cart_checkout()
 * @method string wishlist_add(ProductPage $product)
 * @method string wishlist_remove(ProductPage $product)
 * @method string wishlist_now(ProductPage $product)
 * @method string provider_success(array $params = [])
 * @method string provider_payment(array $params = [])
 */
class Urls
{
    public function __call(string $name, array $arguments): mixed
    {
        if ($name === 'login_magic') {
            $name = 'magiclink';
        }

        if (method_exists(Router::class, $name)) {
            return Router::$name(...$arguments);
        }

        return null;
    }
}
