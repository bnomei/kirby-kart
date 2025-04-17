<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Kirby\Cms\Page;
use ProductPage;

/**
 * @method string account_delete()
 * @method string captcha()
 * @method string cart()
 * @method string cart_add(ProductPage $product)
 * @method string cart_buy(ProductPage $product)
 * @method string cart_checkout()
 * @method string cart_later(ProductPage $product)
 * @method string cart_remove(ProductPage $product)
 * @method string csrf()
 * @method string kart()
 * @method string login(?string $email = null)
 * @method string login_magic(?string $email = null)
 * @method string logout()
 * @method string magiclink(?string $email = null)
 * @method string provider_payment(array $params = [])
 * @method string provider_success(array $params = [])
 * @method string signup_magic(?string $email = null)
 * @method string sync(Page|null|string $page)
 * @method string wishlist_add(ProductPage $product)
 * @method string wishlist_now(ProductPage $product)
 * @method string wishlist_remove(ProductPage $product)
 */
class Urls implements Kerbs
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

    public function toKerbs(): array
    {
        return [
            'account_delete' => $this->account_delete(),
            'captcha' => $this->captcha(),
            'cart' => $this->cart(),
            'cart_checkout' => $this->cart_checkout(),
            'csrf' => $this->csrf(),
            'login' => $this->login(),
            'login_magic' => $this->login_magic(),
            'logout' => $this->logout(),
            'signup_magic' => $this->signup_magic(),
        ];
    }
}
