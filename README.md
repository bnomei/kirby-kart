# Kirby Kart

[![Kirby 6](https://flat.badgen.net/badge/Kirby/6?color=ECC748)](https://getkirby.com)
![PHP 8.3](https://flat.badgen.net/badge/PHP/8.3?color=4E5B93&icon=php&label)
![Release](https://flat.badgen.net/packagist/v/bnomei/kirby-kart?color=ae81ff&icon=github&label)
![Downloads](https://flat.badgen.net/packagist/dt/bnomei/kirby-kart?color=272822&icon=github&label)
![Unittests](https://github.com/bnomei/kirby-kart/actions/workflows/pest-tests.yml/badge.svg)
![PHPStan](https://github.com/bnomei/kirby-kart/actions/workflows/phpstan.yml/badge.svg)
[![Discord](https://flat.badgen.net/badge/discord/bnomei?color=7289da&icon=discord&label)](https://discordapp.com/users/bnomei)
[![Buy License](https://flat.badgen.net/badge/icon/Buy%20License?icon=lemonsqueezy&color=FFC233&label=$)](https://buy-kart.bnomei.com)

Streamlined E-Commerce Shopping Cart Solution

## Kirby 6 testing branch

This branch prepares Kart **6.0.0** (unreleased), targets Kirby **6.0.0-beta.1**, and requires PHP 8.3 or newer. The plugin version and Kirby's beta dependency pin are separate. It is not a production release.
Run `composer kirby`, `composer testBefore`, then `KIRBY_HOST=kart.test composer test` to test the turtle fixtures.
Run `composer testAfter` to remove the generated fixtures.

Existing `kart-magic-link` entries in `auth.methods` continue to enable frontend login/signup, in both list and map configurations. No configuration migration is required:

```php
'auth' => [
    'methods' => ['password', 'kart-magic-link'],
],
```

Alternatively, include `kart-magic-link` in `auth.challenges`. Either option enables the frontend endpoints; omitting it from both disables them. The method flag adds no Panel login option.

Kart's frontend routes send signed standalone login links, independent of Panel second-factor requirements. Registering the challenge in `auth.challenges` also makes it available to Kirby's native authentication flows, where it uses Kirby's email-code form and second-factor rules.

## Installation

For testing before publication:

- unzip [kirby-6.zip](https://github.com/bnomei/kirby-kart/archive/refs/heads/kirby-6.zip) as folder `site/plugins/kirby-kart` or
- `git submodule add -b kirby-6 https://github.com/bnomei/kirby-kart.git site/plugins/kirby-kart` or
- `composer require bnomei/kirby-kart:dev-kirby-6` (Composer's branch selector, not the plugin version).

After 6.0.0 is published, use `composer require bnomei/kirby-kart:^6.0`. Kirby 5 sites should stay on Kart 5.x.

## Licensing

Kirby Kart is a commercial plugin that requires a license. You can install and test the plugin locally without a
license. However, production environments require a valid license. You
can [purchase a license here](https://buy-kart.bnomei.com).

## Quickstart

1. ⬇️ Install the Kirby 6 testing branch using one of the methods above.
2. 🏎️ Access the `/kart`-route on your local setup to initialize Kart and start the demo.
3. 🪞 Copy the snippets and templates from the plugin into your project `site`-folder.
4. 🎨 Modify the copied snippets and templates to match your projects style and site structure.
5. 🔗 Link Kart to a Provider (like Stripe or Paypal) and fetch your products from them. Alternativly use Kirby to manage
   the products and the provider only for checkout.
6. 💅 Enhance your products within Kirby with additional text, images and downloadable files.
7. 🪪 Buy a license for Kirby Kart, register it in the config-file and go online with your shop.
8. 💰 The streamlined shopping cart, invoices and downloads will make your customers happy.
9. 📈 Track orders and remaining stock within Kirby.

## Documentation

You can find the full [documentation for Kirby Kart](https://kart.bnomei.com) on its dedicated website.

## Provider feature matrix

| Provider      | Hosted Checkout | Order Flow          | Product sync | Hosted Portal | Invoice URL |
|---------------|-----------------|---------------------|--------------|---------------|-------------|
| chargebee     | ✅               | return URL          | ✅            | ✅             | ✅           |
| checkout      | ✅               | return URL          | -            | -             | -           |
| fastspring    | external        | -                   | ✅            | -             | -           |
| gumroad       | external        | webhook             | ✅            | -             | ✅           |
| invoice_ninja | ✅               | webhook             | ✅            | -             | ✅           |
| lemonsqueezy  | ✅               | return URL, webhook | ✅            | ✅             | ✅           |
| mollie        | ✅               | return URL          | -            | -             | -           |
| paddle        | JS snippet      | return URL          | ✅            | ✅             | ✅           |
| paypal        | ✅               | return URL          | ✅            | -             | -           |
| polar         | ✅               | return URL          | ✅            | -             | ✅           |
| shopify       | ✅               | webhook             | ✅            | -             | -           |
| snipcart      | JS snippet      | webhook             | ✅            | -             | -           |
| square        | ✅               | return URL          | -            | -             | ✅           |
| stripe        | ✅               | return URL          | ✅            | ✅             | ✅           |
| sumup         | JS snippet      | return URL          | -            | -             | -           |

### Shopify product metafields

Shopify product metafields can be added to the product's raw provider data with an allowlist of `namespace.key` values:

```php
return [
    'bnomei.kart.providers.shopify.metafields' => [
        'custom.product_details',
    ],
];
```

The Shopify app needs the `read_products` and `read_metaobjects` access scopes. Scalar values and one level of referenced
metaobjects are available under `$product->raw()->yaml()['metafields']`; nested references are not resolved.

## Disclaimer

This plugin is provided "as is" with no guarantee. You can use it at your own risk and always test it before using it in
a production environment. If you find any issues,
please [create a new issue](https://github.com/bnomei/kirby-kart/issues/new).

## License

Kirby Kart License © 2025-PRESENT Bruno Meilick
