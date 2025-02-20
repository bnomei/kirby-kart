<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;
use Bnomei\Kart\VirtualPage;
use Kirby\Filesystem\F;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

class Stripe extends Provider
{
    protected string $name = ProviderEnum::STRIPE->value;

    public function checkout(): string
    {
        return ''; // TODO: init checkout for current cart
    }

    public function fetchProducts(): array
    {
        $products = [];
        $cursor = null;

        while (true) {
            // https://docs.stripe.com/api/products/list?lang=curl
            $remote = Remote::get('https://api.stripe.com/v1/products', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$this->option('secret_key'),
                ],
                'data' => array_filter([
                    'active' => 'true',
                    'limit' => 100,
                    'starting_after' => $cursor,
                    'expand' => ['data.default_price'],
                ]),
            ]);

            if ($remote->code() !== 200) {
                break;
            }

            $json = $remote->json();
            if (! is_array($json)) {
                break;
            }

            foreach (A::get($json, 'data') as $product) {
                $cursor = A::get($product, 'id');
                $products[$cursor] = $product;
            }

            if (! A::get($json, 'has_more')) {
                break;
            }
        }

        $images = $this->kirby->site()->kart()->page(ContentPageEnum::PRODUCTS)->images();

        return array_map(function ($data) use ($images) {
            $page = (new VirtualPage($data, [
                // MAP: kirby <=> stripe
                'id' => 'id',
                'uuid' => 'id',
                'title' => 'name',
                'content' => [
                    'description' => 'description',
                    'price' => fn ($i) => A::get($i, 'default_price.unit_amount', 0) / 100.0,
                    'gallery' => fn ($i) => array_map(
                        // fn ($url) => $this->kirby->option('bnomei.kart.products.page').'/'.F::filename($url), // simple but does not resolve change in extension
                        fn ($url) => $images->filter('name', F::name($url))->first()?->uuid()->toString(), // slower but better results
                        A::get($i, 'images', [])),
                ],
            ]));
            $page->num(1); // make listed
            $page->template('product');
            $page->model('product');
            $page->raw($data);
            $page->content['tax'] = 0;

            return $page->toArray();
        }, $products);
    }

    public function fetchOrders(): array
    {
        return [];
    }

    public function fetchStocks(): array
    {
        return [];
    }
}
