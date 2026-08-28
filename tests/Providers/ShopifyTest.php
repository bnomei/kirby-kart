<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Models\ProductPage;
use Bnomei\Kart\Provider\Shopify;
use Kirby\Cms\App;
use Kirby\Data\Yaml;
use Kirby\Http\Remote;

uses()->group('providers');

beforeEach(function (): void {
    kirby()->cache('bnomei.kart.shopify')->flush();
    kart()->setOption('providers.shopify.api_version', '');
    kart()->setOption('providers.shopify.metafields', []);
});

afterEach(function (): void {
    kirby()->cache('bnomei.kart.shopify')->flush();
});

function shopifyAdminHeadersForTest(Shopify $shopify): array
{
    $method = new ReflectionMethod(Shopify::class, 'adminHeaders');

    return $method->invoke($shopify);
}

function shopifyEndpointForTest(Shopify $shopify, string $method): string
{
    $method = new ReflectionMethod(Shopify::class, $method);

    return $method->invoke($shopify);
}

it('uses the fallback Shopify API version by default', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.api_version', '');

    $shopify = new Shopify(kirby());

    expect(shopifyEndpointForTest($shopify, 'adminEndpoint'))->toBe('https://example.myshopify.com/admin/api/2024-07')
        ->and(shopifyEndpointForTest($shopify, 'storefrontEndpoint'))->toBe('https://example.myshopify.com/api/2024-07/graphql.json');
});

it('allows overriding the Shopify API version from config', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.api_version', '2026-01');

    $shopify = new Shopify(kirby());

    expect(shopifyEndpointForTest($shopify, 'adminEndpoint'))->toBe('https://example.myshopify.com/admin/api/2026-01')
        ->and(shopifyEndpointForTest($shopify, 'storefrontEndpoint'))->toBe('https://example.myshopify.com/api/2026-01/graphql.json');
});

it('requests a fresh Shopify admin token from client credentials for each admin request', function (): void {
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');

    $shopify = new class(kirby()) extends Shopify
    {
        public array $requests = [];

        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            $this->requests[] = [$clientId, $clientSecret];

            return 'fresh-token-'.count($this->requests);
        }
    };

    $first = shopifyAdminHeadersForTest($shopify);
    $second = shopifyAdminHeadersForTest($shopify);

    expect($first['X-Shopify-Access-Token'])->toBe('fresh-token-1')
        ->and($second['X-Shopify-Access-Token'])->toBe('fresh-token-2')
        ->and($shopify->requests)->toBe([
            ['client-id', 'client-secret'],
            ['client-id', 'client-secret'],
        ]);
});

it('requires Shopify client credentials for admin headers', function (): void {
    kart()->setOption('providers.shopify.client_id', '');
    kart()->setOption('providers.shopify.client_secret', '');

    $shopify = new class(kirby()) extends Shopify
    {
        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            return 'unexpected-token';
        }
    };

    expect(fn () => shopifyAdminHeadersForTest($shopify))
        ->toThrow(RuntimeException::class, 'Shopify client_id and client_secret are required to request an Admin API token');
});

it('skips Shopify product sync without a store domain', function (): void {
    kart()->setOption('providers.shopify.store_domain', '');
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');

    $shopify = new class(kirby()) extends Shopify
    {
        public bool $requested = false;

        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            $this->requested = true;

            return 'unexpected-token';
        }
    };

    expect($shopify->fetchProducts())->toBe([])
        ->and($shopify->requested)->toBeFalse();
});

it('skips Shopify product sync without client credentials', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.client_id', '');
    kart()->setOption('providers.shopify.client_secret', '');

    $shopify = new class(kirby()) extends Shopify
    {
        public bool $requested = false;

        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            $this->requested = true;

            return 'unexpected-token';
        }
    };

    expect($shopify->fetchProducts())->toBe([])
        ->and($shopify->requested)->toBeFalse();
});

it('throws a Shopify product sync error when the admin token request fails', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');
    kirby()->cache('bnomei.kart.shopify')->flush();

    $shopify = new class(kirby()) extends Shopify
    {
        public bool $requested = false;

        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            $this->requested = true;

            throw new RuntimeException('token failed');
        }
    };

    expect(fn () => $shopify->products())
        ->toThrow(RuntimeException::class, 'token failed')
        ->and($shopify->requested)->toBeTrue();
});

it('keeps cached Shopify products when a refresh fails', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');
    kirby()->cache('bnomei.kart.shopify')->flush();

    $shopify = new class(kirby()) extends Shopify
    {
        public bool $requested = false;

        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            $this->requested = true;

            throw new RuntimeException('token failed');
        }
    };

    $cached = [
        [
            'id' => 'cached-product',
        ],
    ];
    $shopify->cache()->set('products', $cached, 5);

    expect(fn () => $shopify->sync('products'))
        ->toThrow(RuntimeException::class, 'token failed');

    expect($shopify->products())->toBe($cached)
        ->and($shopify->requested)->toBeTrue();
});

it('drafts Shopify product pages removed by a successful sync', function (): void {
    kart()->setOption('products.product.missing', 'draft');
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');
    kirby()->cache('bnomei.kart.shopify')->flush();

    $productId = strval(random_int(100_000, 999_999));
    $shopify = new class(kirby(), $productId) extends Shopify
    {
        public int $requests = 0;

        public function __construct(App $kirby, private readonly string $productId)
        {
            parent::__construct($kirby);
        }

        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            return 'fresh-token';
        }

        protected function requestAdminProducts(?string $pageInfo): Remote
        {
            $remote = new Remote('https://example.myshopify.com/admin/api/2024-07/products.json', [
                'test' => true,
            ]);
            $remote->info['http_code'] = 200;
            $remote->content = json_encode([
                'products' => $this->requests++ === 0 ? [[
                    'id' => $this->productId,
                    'title' => 'Removed Shopify product '.$this->productId,
                    'variants' => [],
                    'images' => [],
                ]] : [],
            ]);

            return $remote;
        }
    };
    $page = null;

    try {
        $shopify->sync('products');
        $page = page('products')->childrenAndDrafts()->findBy('raw_provider', 'shopify|'.$productId);

        expect($page)->toBeInstanceOf(ProductPage::class)
            ->and($page->isListed())->toBeTrue();

        $shopify->sync('products');
        $page = page('products')->childrenAndDrafts()->findBy('raw_provider', 'shopify|'.$productId);

        expect($page)->toBeInstanceOf(ProductPage::class)
            ->and($page->isDraft())->toBeTrue();
    } finally {
        kirby()->impersonate('kirby', fn () => $page?->delete(true));
    }
});

it('throws Shopify products API errors', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');

    $shopify = new class(kirby()) extends Shopify
    {
        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            return 'fresh-token';
        }

        protected function requestAdminProducts(?string $pageInfo): Remote
        {
            $remote = new Remote('https://example.myshopify.com/admin/api/2024-07/products.json', [
                'test' => true,
            ]);
            $remote->info['http_code'] = 403;
            $remote->content = json_encode([
                'errors' => '[API] This action requires merchant approval for read_products scope.',
            ]);

            return $remote;
        }
    };

    expect(fn () => $shopify->products())
        ->toThrow(
            RuntimeException::class,
            'Shopify Admin products request failed with status 403: [API] This action requires merchant approval for read_products scope.'
        );
});

it('throws when Shopify product pagination cannot advance', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');

    $shopify = new class(kirby()) extends Shopify
    {
        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            return 'fresh-token';
        }

        protected function requestAdminProducts(?string $pageInfo): Remote
        {
            $remote = new Remote('https://example.myshopify.com/admin/api/2024-07/products.json', [
                'test' => true,
            ]);
            $remote->info['http_code'] = 200;
            $remote->headers = [
                'Link' => '<https://example.myshopify.com/admin/api/2024-07/products.json?limit=250>; rel="next"',
            ];
            $remote->content = json_encode([
                'products' => [],
            ]);

            return $remote;
        }
    };

    expect(fn () => $shopify->fetchProducts())
        ->toThrow(RuntimeException::class, 'Shopify product pagination cannot advance');
});

it('maps Shopify option names and values to variant tags', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');

    $shopify = new class(kirby()) extends Shopify
    {
        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            return 'fresh-token';
        }

        protected function requestAdminProducts(?string $pageInfo): Remote
        {
            $remote = new Remote('https://example.myshopify.com/admin/api/2024-07/products.json', [
                'test' => true,
            ]);
            $remote->info['http_code'] = 200;
            $remote->content = json_encode([
                'products' => [[
                    'id' => 123,
                    'title' => 'Cup',
                    'options' => [
                        ['name' => 'Material', 'position' => 1],
                        ['name' => 'Color', 'position' => 2],
                    ],
                    'variants' => [
                        [
                            'id' => 456,
                            'title' => 'Paper / Red',
                            'option1' => 'Paper',
                            'option2' => 'Red',
                            'option3' => null,
                            'price' => '12.50',
                        ],
                        [
                            'id' => 789,
                            'title' => 'Plastic / Blue',
                            'option1' => 'Plastic',
                            'option2' => 'Blue',
                            'option3' => null,
                            'price' => '15.00',
                        ],
                    ],
                    'images' => [],
                ]],
            ]);

            return $remote;
        }
    };

    $products = $shopify->fetchProducts();
    $product = reset($products);
    $variants = Yaml::decode($product['content']['variants']);

    expect($variants)->toMatchArray([
        [
            'price_id' => '456',
            'variant' => 'Material:Paper,Color:Red',
            'price' => 12.5,
        ],
        [
            'price_id' => '789',
            'variant' => 'Material:Plastic,Color:Blue',
            'price' => 15.0,
        ],
    ]);

    $productPage = new ProductPage([
        'slug' => 'cup',
        'parent' => page('products'),
        'template' => 'product',
        'model' => 'product',
        'content' => $product['content'],
    ]);
    $variantForProduct = new ReflectionMethod(Shopify::class, 'variantForProduct');

    expect($productPage->variantGroups())->toBe([
        'Color' => [
            'Blue' => 'Blue',
            'Red' => 'Red',
        ],
        'Material' => [
            'Paper' => 'Paper',
            'Plastic' => 'Plastic',
        ],
    ])
        ->and($variantForProduct->invoke($shopify, $productPage, '456', 'Paper / Red'))
        ->toBe('Color:Red,Material:Paper')
        ->and($variantForProduct->invoke($shopify, $productPage, 'missing', 'Unknown'))
        ->toBe('Unknown');
});

it('adds configured Shopify product metafields and metaobjects to raw provider data', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');
    kart()->setOption('providers.shopify.metafields', [
        'custom.product_details',
        'custom.material',
        'custom.primary_detail',
    ]);

    $shopify = new class(kirby()) extends Shopify
    {
        public array $metafieldRequests = [];

        public array $metaobjectRequests = [];

        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            return 'fresh-token';
        }

        protected function requestAdminProducts(?string $pageInfo): Remote
        {
            $remote = new Remote('https://example.myshopify.com/admin/api/2024-07/products.json', [
                'test' => true,
            ]);
            $remote->info['http_code'] = 200;
            $remote->content = json_encode([
                'products' => [[
                    'id' => 123,
                    'title' => 'Cup',
                    'variants' => [],
                    'images' => [],
                ]],
            ]);

            return $remote;
        }

        protected function requestAdminProductMetafields(array $productIds, array $metafields): Remote
        {
            $this->metafieldRequests[] = compact('productIds', 'metafields');

            $remote = new Remote('https://example.myshopify.com/admin/api/2024-07/graphql.json', [
                'test' => true,
            ]);
            $remote->info['http_code'] = 200;
            $remote->content = json_encode([
                'data' => [
                    'nodes' => [[
                        'legacyResourceId' => '123',
                        'metafield0' => [
                            'namespace' => 'custom',
                            'key' => 'product_details',
                            'type' => 'list.metaobject_reference',
                            'value' => '["gid://shopify/Metaobject/456"]',
                        ],
                        'metafield1' => [
                            'namespace' => 'custom',
                            'key' => 'material',
                            'type' => 'single_line_text_field',
                            'value' => 'Ceramic',
                        ],
                        'metafield2' => [
                            'namespace' => 'custom',
                            'key' => 'primary_detail',
                            'type' => 'metaobject_reference',
                            'value' => 'gid://shopify/Metaobject/457',
                        ],
                    ]],
                ],
            ]);

            return $remote;
        }

        protected function requestAdminMetaobjects(array $metaobjectIds): Remote
        {
            $this->metaobjectRequests[] = $metaobjectIds;

            $remote = new Remote('https://example.myshopify.com/admin/api/2024-07/graphql.json', [
                'test' => true,
            ]);
            $remote->info['http_code'] = 200;
            $remote->content = json_encode([
                'data' => [
                    'nodes' => [
                        [
                            'id' => 'gid://shopify/Metaobject/456',
                            'type' => 'product_details_entry',
                            'handle' => 'dishwasher-safe',
                            'displayName' => 'Dishwasher safe',
                            'fields' => [
                                [
                                    'key' => 'title',
                                    'type' => 'single_line_text_field',
                                    'value' => 'Dishwasher safe',
                                ],
                                [
                                    'key' => 'content',
                                    'type' => 'multi_line_text_field',
                                    'value' => 'Safe for domestic dishwashers.',
                                ],
                            ],
                        ],
                        [
                            'id' => 'gid://shopify/Metaobject/457',
                            'type' => 'product_details_entry',
                            'handle' => 'microwave-safe',
                            'displayName' => 'Microwave safe',
                            'fields' => [],
                        ],
                    ],
                ],
            ]);

            return $remote;
        }
    };

    $products = $shopify->fetchProducts();
    $product = reset($products);
    $raw = Yaml::decode($product['content']['raw']);

    expect($shopify->metafieldRequests)->toBe([[
        'productIds' => ['123'],
        'metafields' => [
            ['namespace' => 'custom', 'key' => 'product_details'],
            ['namespace' => 'custom', 'key' => 'material'],
            ['namespace' => 'custom', 'key' => 'primary_detail'],
        ],
    ]])
        ->and($shopify->metaobjectRequests)->toBe([[
            'gid://shopify/Metaobject/456',
            'gid://shopify/Metaobject/457',
        ]])
        ->and($raw['metafields']['custom']['material'])->toBe([
            'key' => 'material',
            'namespace' => 'custom',
            'type' => 'single_line_text_field',
            'value' => 'Ceramic',
        ])
        ->and($raw['metafields']['custom']['product_details'])->toMatchArray([
            'key' => 'product_details',
            'namespace' => 'custom',
            'type' => 'list.metaobject_reference',
            'references' => [[
                'id' => 'gid://shopify/Metaobject/456',
                'type' => 'product_details_entry',
                'handle' => 'dishwasher-safe',
                'displayName' => 'Dishwasher safe',
                'fields' => [
                    [
                        'key' => 'title',
                        'type' => 'single_line_text_field',
                        'value' => 'Dishwasher safe',
                    ],
                    [
                        'key' => 'content',
                        'type' => 'multi_line_text_field',
                        'value' => 'Safe for domestic dishwashers.',
                    ],
                ],
            ]],
        ])
        ->and($raw['metafields']['custom']['primary_detail']['reference'])->toMatchArray([
            'id' => 'gid://shopify/Metaobject/457',
            'type' => 'product_details_entry',
            'handle' => 'microwave-safe',
            'displayName' => 'Microwave safe',
        ]);
});

it('reports Shopify GraphQL errors while fetching configured product metafields', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');
    kart()->setOption('providers.shopify.metafields', ['custom.product_details']);

    $shopify = new class(kirby()) extends Shopify
    {
        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            return 'fresh-token';
        }

        protected function requestAdminProducts(?string $pageInfo): Remote
        {
            $remote = new Remote('https://example.myshopify.com/admin/api/2024-07/products.json', [
                'test' => true,
            ]);
            $remote->info['http_code'] = 200;
            $remote->content = json_encode([
                'products' => [[
                    'id' => 123,
                    'title' => 'Cup',
                ]],
            ]);

            return $remote;
        }

        protected function requestAdminProductMetafields(array $productIds, array $metafields): Remote
        {
            $remote = new Remote('https://example.myshopify.com/admin/api/2024-07/graphql.json', [
                'test' => true,
            ]);
            $remote->info['http_code'] = 200;
            $remote->content = json_encode([
                'errors' => [[
                    'message' => 'Access denied for Metaobject field. Required access: read_metaobjects.',
                ]],
            ]);

            return $remote;
        }
    };

    expect(fn () => $shopify->fetchProducts())
        ->toThrow(
            RuntimeException::class,
            'Shopify Admin product metafields request failed: Access denied for Metaobject field. Required access: read_metaobjects.'
        );
});

it('resolves complete Shopify metaobject lists in chunks while preserving order', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');
    kart()->setOption('providers.shopify.metafields', ['custom.product_details']);

    $referenceIds = array_map(
        fn (int $id) => 'gid://shopify/Metaobject/'.$id,
        range(1, 251)
    );

    $shopify = new class(kirby(), $referenceIds) extends Shopify
    {
        public array $metaobjectRequests = [];

        public function __construct($kirby, private array $referenceIds)
        {
            parent::__construct($kirby);
        }

        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            return 'fresh-token';
        }

        protected function requestAdminProducts(?string $pageInfo): Remote
        {
            $remote = new Remote('https://example.myshopify.com/admin/api/2024-07/products.json', [
                'test' => true,
            ]);
            $remote->info['http_code'] = 200;
            $remote->content = json_encode([
                'products' => [[
                    'id' => 123,
                    'title' => 'Cup',
                ]],
            ]);

            return $remote;
        }

        protected function requestAdminProductMetafields(array $productIds, array $metafields): Remote
        {
            $remote = new Remote('https://example.myshopify.com/admin/api/2024-07/graphql.json', [
                'test' => true,
            ]);
            $remote->info['http_code'] = 200;
            $remote->content = json_encode([
                'data' => [
                    'nodes' => [[
                        'legacyResourceId' => '123',
                        'metafield0' => [
                            'namespace' => 'custom',
                            'key' => 'product_details',
                            'type' => 'list.metaobject_reference',
                            'value' => json_encode($this->referenceIds),
                        ],
                    ]],
                ],
            ]);

            return $remote;
        }

        protected function requestAdminMetaobjects(array $metaobjectIds): Remote
        {
            $this->metaobjectRequests[] = $metaobjectIds;

            $remote = new Remote('https://example.myshopify.com/admin/api/2024-07/graphql.json', [
                'test' => true,
            ]);
            $remote->info['http_code'] = 200;
            $remote->content = json_encode([
                'data' => [
                    'nodes' => array_map(fn (string $id) => [
                        'id' => $id,
                        'type' => 'product_details_entry',
                        'handle' => basename($id),
                        'displayName' => basename($id),
                        'fields' => [],
                    ], $metaobjectIds),
                ],
            ]);

            return $remote;
        }
    };

    $products = $shopify->fetchProducts();
    $product = reset($products);
    $raw = Yaml::decode($product['content']['raw']);
    $references = $raw['metafields']['custom']['product_details']['references'];

    expect(array_map('count', $shopify->metaobjectRequests))->toBe([249, 2])
        ->and($references)->toHaveCount(251)
        ->and($references[0]['id'])->toBe('gid://shopify/Metaobject/1')
        ->and($references[250]['id'])->toBe('gid://shopify/Metaobject/251');
});

it('cost bounds Shopify product metafield requests', function (): void {
    kart()->setOption('providers.shopify.store_domain', 'example.myshopify.com');
    kart()->setOption('providers.shopify.client_id', 'client-id');
    kart()->setOption('providers.shopify.client_secret', 'client-secret');
    kart()->setOption('providers.shopify.metafields', array_map(
        fn (int $key) => 'custom.field_'.$key,
        range(1, 10)
    ));

    $products = array_map(fn (int $id) => [
        'id' => $id,
        'title' => 'Product '.$id,
    ], range(1, 60));

    $shopify = new class(kirby(), $products) extends Shopify
    {
        public array $metafieldRequests = [];

        public function __construct($kirby, private array $products)
        {
            parent::__construct($kirby);
        }

        protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
        {
            return 'fresh-token';
        }

        protected function requestAdminProducts(?string $pageInfo): Remote
        {
            $remote = new Remote('https://example.myshopify.com/admin/api/2024-07/products.json', [
                'test' => true,
            ]);
            $remote->info['http_code'] = 200;
            $remote->content = json_encode(['products' => $this->products]);

            return $remote;
        }

        protected function requestAdminProductMetafields(array $productIds, array $metafields): Remote
        {
            $this->metafieldRequests[] = [count($productIds), count($metafields)];

            $remote = new Remote('https://example.myshopify.com/admin/api/2024-07/graphql.json', [
                'test' => true,
            ]);
            $remote->info['http_code'] = 200;
            $remote->content = json_encode([
                'data' => [
                    'nodes' => array_map(fn (string $productId) => [
                        'legacyResourceId' => $productId,
                    ], $productIds),
                ],
            ]);

            return $remote;
        }
    };

    expect($shopify->fetchProducts())->toHaveCount(60)
        ->and($shopify->metafieldRequests)->toBe([
            [50, 10],
            [10, 10],
        ]);
});
