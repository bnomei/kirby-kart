<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\CartLine;
use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;
use Bnomei\Kart\Router;
use Bnomei\Kart\VirtualPage;
use Kirby\Filesystem\F;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

class Stripe extends Provider
{
    protected string $name = ProviderEnum::STRIPE->value;

    public function checkout(): ?string
    {
        $options = $this->option('checkout_options', false);
        if ($options instanceof \Closure) {
            $options = $options($this->kart);
        }

        // https://docs.stripe.com/api/checkout/sessions/create?lang=curl
        $remote = Remote::post('https://api.stripe.com/v1/checkout/sessions', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
            'data' => array_filter(array_merge([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'currency' => strtolower($this->kart->currency()),
                'customer_email' => $this->kirby->user()?->email(),
                'success_url' => $this->kirby->url().'/'.Router::PROVIDER_SUCCESS.'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $this->kirby->url().'/'.Router::PROVIDER_CANCEL,
                'line_items' => $this->kart->cart()->lines()->values(fn (CartLine $l) => [
                    'price' => A::get($l->product()?->raw()->yaml(), 'default_price.id'), // @phpstan-ignore-line
                    'quantity' => $l->quantity(),
                ]),
            ], $options)),
        ]);

        return parent::checkout() && $remote->code() === 200 ?
            $remote->json()['url'] : null;
    }

    public function completed(array $data = []): array
    {
        // get session from current session id param
        $sessionId = get('session_id');
        if (! $sessionId || ! is_string($sessionId)) {
            return [];
        }

        $remote = Remote::get('https://api.stripe.com/v1/checkout/sessions/'.$sessionId, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ]]);
        if ($remote->code() !== 200) {
            return [];
        }

        $json = $remote->json();

        $data = array_merge($data, array_filter([
            // 'session_id' => $sessionId,
            'email' => A::get($json, 'customer_email'),
            'paidDate' => date('Y-m-d H:i:s', A::get($json, 'created', time())),
            'paymentMethod' => implode(',', A::get($json, 'payment_method_types', [])),
            'paymentComplete' => A::get($json, 'payment_status') === 'paid',
        ]));

        $remote = Remote::get('https://api.stripe.com/v1/checkout/sessions/'.$sessionId.'/line_items', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ], [
                'limit' => $this->kart->lines()->count(),
            ]]);

        if ($remote->code() !== 200) {
            return [];
        }

        $json = $remote->json();
        foreach (A::get($json, 'data') as $line) {
            $data['items'][] = [
                'key' => A::get($line, 'price.product'),
                'quantity' => A::get($line, 'quantity'),
                'price' => A::get($line, 'price.unit_amount', 0) / 100.0,
                'total' => 0, // TODO:
                'subtotal' => 0, // TODO:
                'tax' => 0, // TODO:
                'discount' => 0, // TODO:
            ];
        }

        return parent::completed($data);
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
                    'Authorization' => 'Bearer '.strval($this->option('secret_key')),
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
            //            $page->template('product');
            //            $page->model('product');
            $page->raw($data);
            $page->content['tax'] = 0;

            return $page->toArray();
        }, $products);
    }
}
