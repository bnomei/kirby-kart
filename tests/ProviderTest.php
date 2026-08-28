<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Models\ProductPage;
use Bnomei\Kart\Provider as BaseProvider;
use Bnomei\Kart\Provider\Chargebee;
use Bnomei\Kart\Provider\Fastspring;
use Bnomei\Kart\Provider\Gumroad;
use Bnomei\Kart\Provider\Invoiceninja;
use Bnomei\Kart\Provider\Kirby;
use Bnomei\Kart\Provider\Lemonsqueezy;
use Bnomei\Kart\Provider\Paddle;
use Bnomei\Kart\Provider\Paypal;
use Bnomei\Kart\Provider\Polar;
use Bnomei\Kart\Provider\Shopify;
use Bnomei\Kart\Provider\Snipcart;
use Bnomei\Kart\Provider\Stripe;
use Kirby\Cms\App;
use Kirby\Toolkit\Str;

beforeEach(function (): void {
    $this->p = new Kirby(kirby());
});

it('can have a title and name', function (): void {
    expect($this->p->title())->toBe('Kirby Cms')
        ->and($this->p->name())->toBe('kirby_cms');
});

it('can have virtual pages', function (): void {
    expect($this->p->virtual())->toBeFalse();
});

it('can have resolvable options', function (): void {
    expect($this->p->option('doesnotexist', true))->toBeNull();
});

it('can find images in the media pool', function (): void {
    expect($this->p->findImagesFromUrls(['logo.svg']))->toBeEmpty();
});

it('can set and read user data', function (): void {
    $customer = kirby()->impersonate('kirby', fn () => kirby()->users()->create([
        'email' => Str::random(5).'@kart.test',
        'role' => 'customer',
        'password' => Str::random(16),
    ]));
    expect($customer->isCustomer())->toBeTrue();
    kirby()->impersonate($customer);

    $customer = $this->p->setUserData([
        'customer_id' => 123,
        'ts' => time(),
    ], $customer);
    expect($this->p->userData('customer_id'))->toBe(123)
        ->and($this->p->getUserData($customer)['customer_id'])->toBe(123);

    // clean up
    kirby()->impersonate('kirby', function (): void {
        // kirby()->user($customer->email())?->delete();
    });
});

it('can remove selected user data keys', function (): void {
    $customer = kirby()->impersonate('kirby', fn () => kirby()->users()->create([
        'email' => Str::random(5).'@kart.test',
        'role' => 'customer',
        'password' => Str::random(16),
    ]));
    expect($customer->isCustomer())->toBeTrue();
    kirby()->impersonate($customer);

    $customer = $this->p->setUserData([
        'customer_id' => 123,
        'keep' => 'abc',
        'ts' => time(),
    ], $customer);
    expect($this->p->userData('customer_id'))->toBe(123)
        ->and($this->p->userData('keep'))->toBe('abc');

    $customer = $this->p->removeUserData('customer_id', $customer);
    $data = $this->p->getUserData($customer);
    expect($data)->not()->toHaveKey('customer_id')
        ->and($data)->toHaveKey('keep')
        ->and($data)->toHaveKey('ts');

    $customer = $this->p->removeUserData(['keep', 'ts'], $customer);
    expect($this->p->getUserData($customer))->toBeEmpty();
});

it('can sync the data from the provider', function (): void {
    expect($this->p->sync())->toBeInt()
        ->and($this->p->updatedAt())->toBeString()
        ->and($this->p->updatedAt(ContentPageEnum::PRODUCTS))->toBeString()
        ->and($this->p->updatedAt('products'))->toBeString();
});

it('requires custom providers to opt into authoritative product syncing', function (): void {
    $provider = new class(kirby()) extends BaseProvider
    {
        protected string $name = 'custom';

        public bool $requested = false;

        public function fetchProducts(): array
        {
            $this->requested = true;

            return [];
        }
    };

    expect($provider->sync('products'))->toBeInt()
        ->and($provider->requested)->toBeFalse();
});

it('reconciles missing pages for every product syncing provider', function (): void {
    kart()->setOption('products.product.missing', 'draft');
    $providerNames = [
        'chargebee',
        'fastspring',
        'gumroad',
        'invoice_ninja',
        'lemonsqueezy',
        'paddle',
        'paypal',
        'polar',
        'shopify',
        'snipcart',
        'stripe',
    ];

    foreach ($providerNames as $providerName) {
        $provider = new class(kirby(), $providerName) extends BaseProvider
        {
            public array $catalog = [];

            public function __construct(App $kirby, string $name)
            {
                parent::__construct($kirby);
                $this->name = $name;
            }

            public function fetchProducts(): array
            {
                return $this->catalog;
            }

            protected function canSyncProducts(): bool
            {
                return true;
            }
        };
        $provider->cache()->flush();
        $id = uniqid($providerName.'-', true);
        $product = [
            'id' => $id,
            'model' => 'product',
            'template' => 'product',
            'content' => [
                'title' => 'Synced '.$providerName.' product '.$id,
            ],
        ];
        $page = null;

        try {
            $provider->catalog = [$product];
            $provider->sync('products');
            $page = page('products')->childrenAndDrafts()->findBy('raw_provider', $providerName.'|'.$id);

            expect($page)->toBeInstanceOf(ProductPage::class)
                ->and($page->isListed())->toBeTrue();

            $provider->catalog = [];
            $provider->sync('products');
            $page = page('products')->childrenAndDrafts()->findBy('raw_provider', $providerName.'|'.$id);

            expect($page)->toBeInstanceOf(ProductPage::class)
                ->and($page->isDraft())->toBeTrue();

            $provider->catalog = [$product];
            $provider->sync('products');
            $page = page('products')->childrenAndDrafts()->findBy('raw_provider', $providerName.'|'.$id);

            expect($page)->toBeInstanceOf(ProductPage::class)
                ->and($page->isDraft())->toBeTrue();
        } finally {
            kirby()->impersonate('kirby', fn () => $page?->delete(true));
            $provider->cache()->flush();
        }
    }
});

it('preserves pages and cached products when a product sync fails', function (): void {
    kart()->setOption('products.product.missing', 'draft');
    $provider = new class(kirby()) extends BaseProvider
    {
        protected string $name = 'stripe';

        public array $catalog = [];

        public bool $fails = false;

        public function fetchProducts(): array
        {
            if ($this->fails) {
                throw new RuntimeException('catalog failed');
            }

            return $this->catalog;
        }

        protected function canSyncProducts(): bool
        {
            return true;
        }
    };
    $provider->cache()->flush();
    $id = uniqid('stripe-', true);
    $product = [
        'id' => $id,
        'model' => 'product',
        'template' => 'product',
        'content' => [
            'title' => 'Failed sync product '.$id,
        ],
    ];
    $page = null;

    try {
        $provider->catalog = [$product];
        $provider->sync('products');
        $cached = $provider->cache()->get('products');
        $updatedAt = $provider->cache()->get('updatedAt-products');

        $provider->fails = true;
        expect(fn () => $provider->sync('products'))
            ->toThrow(RuntimeException::class, 'catalog failed');

        $page = page('products')->childrenAndDrafts()->findBy('raw_provider', 'stripe|'.$id);
        expect($page)->toBeInstanceOf(ProductPage::class)
            ->and($page->isListed())->toBeTrue()
            ->and($provider->cache()->get('products'))->toBe($cached)
            ->and($provider->cache()->get('updatedAt-products'))->toBe($updatedAt);
    } finally {
        kirby()->impersonate('kirby', fn () => $page?->delete(true));
        $provider->cache()->flush();
    }
});

it('skips unconfigured product syncing providers', function (): void {
    $providers = [
        'chargebee' => [Chargebee::class, ['api_key', 'site']],
        'fastspring' => [Fastspring::class, ['username', 'password']],
        'gumroad' => [Gumroad::class, ['access_token']],
        'invoice_ninja' => [Invoiceninja::class, ['token']],
        'lemonsqueezy' => [Lemonsqueezy::class, ['secret_key', 'store_id']],
        'paddle' => [Paddle::class, ['secret_key']],
        'paypal' => [Paypal::class, ['client_id', 'client_secret']],
        'polar' => [Polar::class, ['access_token']],
        'shopify' => [Shopify::class, ['store_domain', 'client_id', 'client_secret']],
        'snipcart' => [Snipcart::class, ['secret_key']],
        'stripe' => [Stripe::class, ['secret_key']],
    ];

    foreach ($providers as $name => [$class, $requiredOptions]) {
        foreach ($requiredOptions as $key) {
            kart()->setOption('providers.'.$name.'.'.$key, '');
        }

        $provider = new $class(kirby());
        $method = new ReflectionMethod($provider, 'canSyncProducts');

        expect($method->invoke($provider))->toBeFalse();
    }
});

it('can have a portal url', function (): void {
    expect($this->p->portal())->toBeNull();
});

it('can get the checkout url', function (): void {
    expect($this->p->checkout())->toBeString();
});

it('can get the canceled url', function (): void {
    expect($this->p->canceled())->toBeString();
});

it('can be completed', function (): void {
    $data = [
        'customer_id' => 123,
    ];
    // missing session_id in kirby will cause fail
    expect($this->p->completed($data))->toBeEmpty();

    // happy path
    $name = Str::random(5);
    $data = [
        'session_id' => '123',
        'email' => $name.'@kart.test',
        'name' => $name,
    ];

    kirby()->session()->set('bnomei.kart.'.$this->p->name().'.session_id', $data['session_id']);

    expect($this->p->completed($data))->not()->toBeEmpty();
});

it('accepts completed or authorized payments', function (): void {
    expect($this->p->paymentAccepted(['paymentComplete' => true]))->toBeTrue()
        ->and($this->p->paymentAccepted(['paymentAuthorized' => true]))->toBeTrue()
        ->and($this->p->paymentAccepted(['paymentComplete' => false]))->toBeFalse()
        ->and($this->p->paymentAccepted([]))->toBeFalse();
});

it('can fetch products', function (): void {
    expect($this->p->products())->toBeArray();
});
