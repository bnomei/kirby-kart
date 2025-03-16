<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\CartLine;
use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;
use Bnomei\Kart\Router;
use Bnomei\Kart\VirtualPage;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

class Stripe extends Provider
{
    protected string $name = ProviderEnum::STRIPE->value;

    public function checkout(): string
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
                'success_url' => url(Router::PROVIDER_SUCCESS).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => url(Router::PROVIDER_CANCEL),
                'invoice_creation' => ['enabled' => true],
                'line_items' => $this->kart->cart()->lines()->values(fn (CartLine $l) => [
                    'price' => A::get($l->product()?->raw()->yaml(), 'default_price.id'), // @phpstan-ignore-line
                    'quantity' => $l->quantity(),
                ]),
            ], $options)),
        ]);

        return parent::checkout() && $remote->code() === 200 ?
            $remote->json()['url'] : '/';
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
            ],
            'data' => [
                'expand' => [
                    'customer',
                ],
            ]]);
        if ($remote->code() !== 200) {
            return [];
        }

        $json = $remote->json();

        $data = array_merge($data, array_filter([
            // 'session_id' => $sessionId,
            'email' => A::get($json, 'customer_email'),
            'customer' => [
                'id' => A::get($json, 'customer.id'),
                'email' => A::get($json, 'customer.email'),
                'name' => A::get($json, 'customer.name'),
            ],
            'paidDate' => date('Y-m-d H:i:s', A::get($json, 'created', time())),
            'paymentMethod' => implode(',', A::get($json, 'payment_method_types', [])),
            'paymentComplete' => A::get($json, 'payment_status') === 'paid',
            'invoiceurl' => A::get($json, 'invoice'),
        ]));

        $remote = Remote::get('https://api.stripe.com/v1/checkout/sessions/'.$sessionId.'/line_items', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ], [
                'limit' => 100, // is max without pagination. $this->kart->cart()->lines()->count(),
            ]]);

        if ($remote->code() !== 200) {
            return [];
        }

        $json = $remote->json();
        foreach (A::get($json, 'data') as $line) {
            $data['items'][] = [
                'key' => A::get($line, 'price.product'),
                'quantity' => A::get($line, 'quantity'),
                'price' => round(A::get($line, 'price.unit_amount', 0) / 100.0, 2),
                // these values include the multiplication with quantity
                'total' => round(A::get($line, 'amount_total', 0) / 100.0, 2),
                'subtotal' => round(A::get($line, 'amount_subtotal', 0) / 100.0, 2),
                'tax' => round(A::get($line, 'amount_tax', 0) / 100.0, 2),
                'discount' => round(A::get($line, 'amount_discount', 0) / 100.0, 2),
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

        return array_map(function (array $data) {
            // NOTE: changes here require a cache flush to take effect
            return (new VirtualPage(
                $data,
                [
                    // MAP: kirby <=> stripe
                    'id' => 'id', // id, uuid and slug will be hashed in ProductPage::create based on this `id`
                    'title' => 'name',
                    'content' => [
                        'created' => fn ($i) => date('Y-m-d H:i:s', $i['created']),
                        'description' => 'description',
                        'price' => fn ($i) => A::get($i, 'default_price.unit_amount', 0) / 100.0,
                        'tags' => fn ($i) => A::get($i, 'metadata.tags', []),
                        'categories' => fn ($i) => A::get($i, 'metadata.categories', []),
                        'gallery' => fn ($i) => $this->findImagesFromUrls(
                            A::get($i, 'images', A::get($i, 'metadata.gallery', []))
                        ),
                        'downloads' => fn ($i) => $this->findFilesFromUrls(
                            A::get($i, 'metadata.downloads', [])
                        ),
                    ],
                ],
                $this->kart->page(ContentPageEnum::PRODUCTS))
            )->mixinProduct($data)->toArray();
        }, $products);
    }

    public function portal(?string $returnUrl = null): ?string
    {
        $customer = $this->userData('customerId');
        if (! $customer) {
            return null;
        }

        $remote = Remote::get('https://api.stripe.com/v1/billing_portal/sessions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.strval($this->option('secret_key')),
            ],
            'data' => array_filter([
                'customer' => $customer,
                'return_url' => $returnUrl,
            ]),
        ]);

        if ($remote->code() !== 200) {
            return null;
        }

        $json = $remote->json();

        return A::get($json, 'url');
    }
}
