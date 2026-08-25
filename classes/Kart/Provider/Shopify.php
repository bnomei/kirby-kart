<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Models\ProductPage;
use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;
use Bnomei\Kart\VirtualPage;
use Bnomei\Kart\WebhookResult;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

class Shopify extends Provider
{
    public const API_VERSION = '2024-07';

    protected string $name = ProviderEnum::SHOPIFY->value;

    public function checkout(): string
    {
        $lines = [];
        foreach ($this->kart->cart()->lines() as $line) {
            $product = $line->product();
            $variantData = $product?->variantDataForVariant($line->variant(), resolveImage: false);
            if (! $variantData && $product) {
                $variants = $product->variantData(false);
                $variantData = reset($variants) ?: null;
            }

            $variantId = $variantData['price_id'] ?? null;
            if (! $variantId) {
                continue;
            }

            $lines[] = [
                'merchandiseId' => $this->variantGid(strval($variantId)),
                'quantity' => max(1, $line->quantity()),
            ];
        }

        if (empty($lines)) {
            throw new \RuntimeException('No Shopify cart lines to process');
        }

        $contact = $this->checkoutContact();
        $name = $this->checkoutNameParts();
        $shippingAddress = $this->checkoutShippingAddress();

        $buyerIdentity = array_filter([
            'email' => $contact['email'] ?? $this->kirby->user()?->email(),
            'phone' => $contact['phone'] ?? null,
            'firstName' => $name['first'] ?? null,
            'lastName' => $name['last'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if (! empty($shippingAddress)) {
            $buyerIdentity['deliveryAddressPreferences'] = [[
                'deliveryAddress' => array_filter([
                    'address1' => $shippingAddress['address1'] ?? null,
                    'address2' => $shippingAddress['address2'] ?? null,
                    'city' => $shippingAddress['city'] ?? null,
                    'province' => $shippingAddress['state'] ?? null,
                    'zip' => $shippingAddress['postal_code'] ?? null,
                    'country' => isset($shippingAddress['country']) ? strtoupper($shippingAddress['country']) : null,
                    'firstName' => $shippingAddress['first_name'] ?? ($name['first'] ?? null),
                    'lastName' => $shippingAddress['last_name'] ?? ($name['last'] ?? null),
                    'phone' => $shippingAddress['phone'] ?? ($contact['phone'] ?? null),
                    'company' => $shippingAddress['company'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''),
            ]];
        }

        $input = array_filter([
            'lines' => $lines,
            'buyerIdentity' => empty($buyerIdentity) ? null : $buyerIdentity,
        ]);

        $remote = Remote::post($this->storefrontEndpoint(), [
            'headers' => $this->storefrontHeaders(),
            'data' => json_encode([
                'query' => <<<'GQL'
mutation cartCreate($input: CartInput!) {
  cartCreate(input: $input) {
    cart {
      id
      checkoutUrl
    }
    userErrors {
      field
      message
    }
  }
}
GQL,
                'variables' => [
                    'input' => $input,
                ],
            ]),
        ]);

        $json = $remote->code() === 200 ? $remote->json() : null;
        $errors = is_array($json) ? A::get($json, 'errors', []) : [];
        $userErrors = is_array($json) ? A::get($json, 'data.cartCreate.userErrors', []) : [];

        if (! empty($errors)) {
            throw new \RuntimeException('Shopify cartCreate failed: '.json_encode($errors));
        }
        if (! empty($userErrors)) {
            throw new \RuntimeException('Shopify cartCreate user errors: '.json_encode($userErrors));
        }

        $checkoutUrl = is_array($json) ? A::get($json, 'data.cartCreate.cart.checkoutUrl') : null;
        if (! $checkoutUrl) {
            throw new \RuntimeException('Shopify cartCreate returned no checkoutUrl');
        }

        $channel = strval($this->option('checkout_channel') ?? 'headless-storefront');
        if ($channel !== '') {
            $checkoutUrl .= (str_contains($checkoutUrl, '?') ? '&' : '?').'channel='.$channel;
        }

        return parent::checkout() ? $checkoutUrl : '/';
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }

    public function handleWebhook(array $payload, array $headers = []): WebhookResult
    {
        $raw = strval(A::get($headers, '@raw_body', A::get($payload, '_raw', '')));
        $hmac = trim(strval(A::get($headers, 'x-shopify-hmac-sha256', '')));
        $secret = strval($this->option('webhook_secret'));

        if ($secret === '' || $raw === '' || $hmac === '') {
            return WebhookResult::invalid('missing webhook secret or signature');
        }

        $digest = base64_encode(hash_hmac('sha256', $raw, $secret, true));
        if (! hash_equals($digest, $hmac)) {
            return WebhookResult::invalid('invalid signature');
        }

        $eventId = strval(A::get($headers, 'x-shopify-event-id', A::get($headers, 'x-shopify-webhook-id', '')));
        if ($eventId && $this->isDuplicateWebhook($eventId)) {
            return WebhookResult::ignored('duplicate webhook');
        }
        if ($eventId) {
            $this->rememberWebhook($eventId);
        }

        $topic = strval(A::get($headers, 'x-shopify-topic', ''));
        if ($topic !== 'orders/paid') {
            return WebhookResult::ignored('topic not handled');
        }

        $orderId = strval(A::get($payload, 'id', ''));
        if (! $orderId) {
            return WebhookResult::invalid('missing order id');
        }

        $uuid = kart()->option('products.product.uuid');
        $likey = kart()->option('licenses.license.uuid');

        $items = [];
        foreach (A::get($payload, 'line_items', []) as $line) {
            $quantity = max(1, intval(A::get($line, 'quantity', 1)));
            $unitPrice = round(floatval(A::get($line, 'price_set.shop_money.amount', A::get($line, 'price', 0))), 2);
            $linePrice = round($unitPrice * $quantity, 2);
            $discount = round(floatval(A::get($line, 'total_discount_set.shop_money.amount', 0)), 2);

            $key = null;
            if ($uuid instanceof \Closure) {
                $key = 'page://'.$uuid(null, ['id' => strval(A::get($line, 'product_id'))]);
            }

            $variant = strval(A::get($line, 'variant_title', ''));
            $variantId = strval(A::get($line, 'variant_id', ''));
            $product = $key ? $this->kirby->page($key) : null;
            if ($product instanceof ProductPage && $variantId !== '') {
                $variant = $this->variantForProduct($product, $variantId, $variant);
            }

            $items[] = array_filter([
                'key' => $key ? [$key] : null,
                'variant' => $variant,
                'quantity' => $quantity,
                'price' => $unitPrice,
                'subtotal' => $linePrice,
                'discount' => $discount,
                'total' => max(0, $linePrice - $discount),
                'licensekey' => $likey instanceof \Closure ? $likey(['order' => $payload, 'line' => $line]) : null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        $orderData = array_filter([
            'order_id' => $orderId,
            'paymentId' => 'shopify:'.$orderId,
            'email' => A::get($payload, 'email'),
            'paymentComplete' => A::get($payload, 'financial_status') === 'paid',
            'paidDate' => ($processed = A::get($payload, 'processed_at')) && ($processedTimestamp = strtotime((string) $processed)) !== false
                ? date('Y-m-d H:i:s', $processedTimestamp)
                : null,
            'shop' => A::get($headers, 'x-shopify-shop-domain'),
            'topic' => $topic,
            'items' => $items,
            'raw' => $payload,
        ], fn ($v) => $v !== null && $v !== []);

        return WebhookResult::ok($orderData, 'Shopify webhook processed');
    }

    protected function variantForProduct(ProductPage $product, string $variantId, string $fallback): string
    {
        foreach ($product->variantData(false) as $variant) {
            if (strval(A::get($variant, 'price_id', '')) === $variantId) {
                return strval(A::get($variant, 'variant', $fallback));
            }
        }

        return $fallback;
    }

    public function fetchProducts(): array
    {
        if ($this->canFetchProducts() === false) {
            return [];
        }

        $products = [];
        $pageInfo = null;
        $metafields = $this->productMetafields();

        // REST Admin products listing
        while (true) {
            // https://shopify.dev/docs/api/admin-rest/latest/resources/product#get-products
            $remote = $this->requestAdminProducts($pageInfo);

            $json = $remote->json();
            if ($remote->code() !== 200) {
                $error = is_array($json)
                    ? A::get($json, 'errors', A::get($json, 'error_description', A::get($json, 'error')))
                    : null;
                $message = 'Shopify Admin products request failed with status '.$remote->code();
                if (is_string($error) && $error !== '') {
                    $message .= ': '.$error;
                }

                throw new \RuntimeException($message);
            }
            if (! is_array($json)) {
                throw new \RuntimeException('Shopify Admin products request did not return JSON');
            }

            $pageProducts = A::get($json, 'products', []);
            if (! is_array($pageProducts)) {
                throw new \RuntimeException('Shopify Admin products request did not return products');
            }
            if ($metafields !== []) {
                $pageProducts = $this->fetchProductMetafields($pageProducts, $metafields);
            }

            foreach ($pageProducts as $product) {
                if (! is_array($product)) {
                    throw new \RuntimeException('Shopify Admin products request returned an invalid product');
                }

                $productId = A::get($product, 'id');
                if (! is_int($productId) && ! is_string($productId)) {
                    throw new \RuntimeException('Shopify Admin products request returned a product without an id');
                }

                $products[$productId] = $product;
            }

            // Shopify pagination via Link header
            $headers = $remote->headers();
            $link = A::get($headers, 'Link', A::get($headers, 'link'));
            $nextUrl = null;
            if (is_string($link) && $link !== '') {
                foreach (explode(',', $link) as $part) {
                    if (str_contains($part, 'rel="next"') && preg_match('/<([^>]+)>/', $part, $match)) {
                        $nextUrl = $match[1];
                        break;
                    }
                }
            }

            if (! $nextUrl) {
                break;
            }

            $query = parse_url($nextUrl, PHP_URL_QUERY);
            $pageInfo = null;
            if (is_string($query) && $query !== '') {
                parse_str($query, $params);
                $pageInfo = $params['page_info'] ?? null;
                if (is_string($pageInfo) === false) {
                    $pageInfo = null;
                }
            }
            if (! $pageInfo) {
                break;
            }
        }

        return array_map(fn (array $data) => (new VirtualPage(
            $data,
            [
                'id' => fn ($i) => strval(A::get($i, 'id')),
                'title' => 'title',
                'content' => [
                    'description' => fn ($i) => strip_tags((string) A::get($i, 'body_html', '')),
                    'price' => fn ($i) => floatval(A::get($i, 'variants.0.price', 0)),
                    'tags' => fn ($i) => A::get($i, 'tags', ''),
                    'gallery' => fn ($i) => $this->findImagesFromUrls(array_map(
                        fn ($img) => A::get($img, 'src'), A::get($i, 'images', [])
                    )),
                    'variants' => function ($i) {
                        $optionNames = [];
                        foreach (A::get($i, 'options', []) as $index => $option) {
                            $position = intval(A::get($option, 'position', $index + 1));
                            $name = trim(strval(A::get($option, 'name', '')));
                            if ($position > 0 && $name !== '') {
                                $optionNames[$position] = $name;
                            }
                        }

                        $imageUrlsByImageId = [];
                        $imageUrlsByVariantId = [];
                        foreach (A::get($i, 'images', []) as $image) {
                            $imageUrl = A::get($image, 'src');
                            if (! $imageUrl) {
                                continue;
                            }

                            $imageId = strval(A::get($image, 'id', ''));
                            if ($imageId !== '') {
                                $imageUrlsByImageId[$imageId] = $imageUrl;
                            }

                            foreach (A::get($image, 'variant_ids', []) as $variantId) {
                                $imageUrlsByVariantId[strval($variantId)] = $imageUrl;
                            }
                        }

                        $variants = [];
                        foreach (A::get($i, 'variants', []) as $v) {
                            $variantId = strval(A::get($v, 'id'));
                            $imageId = strval(A::get($v, 'image_id', ''));
                            $variantImageUrl = A::get($imageUrlsByVariantId, $variantId)
                                ?? A::get($imageUrlsByImageId, $imageId);

                            $options = [];
                            foreach ([1, 2, 3] as $position) {
                                $value = trim(strval(A::get($v, 'option'.$position, '')));
                                if ($value !== '') {
                                    $options[] = A::get($optionNames, $position, 'option'.$position).':'.$value;
                                }
                            }

                            $variant = [
                                'price_id' => strval(A::get($v, 'id')),
                                'variant' => empty($options)
                                    ? A::get($v, 'title')
                                    : implode(',', $options),
                                'price' => floatval(A::get($v, 'price', 0)),
                            ];

                            if ($variantImageUrl) {
                                $variantImage = $this->findImagesFromUrls($variantImageUrl);
                                if (! empty($variantImage)) {
                                    $variant['image'] = $variantImage;
                                }
                            }

                            $variants[] = $variant;
                        }

                        return empty($variants) ? null : $variants;
                    },
                ],
            ],
            $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), $products);
    }

    private function fetchProductMetafields(array $products, array $metafields): array
    {
        $productIds = array_values(array_filter(array_map(
            fn (array $product) => strval(A::get($product, 'id', '')),
            $products
        )));
        if ($productIds === []) {
            return $products;
        }

        $metafieldsByProduct = [];
        $referenceIdsByMetafield = [];
        $metaobjectIds = [];

        // Keep each request comfortably below Shopify's 1,000 point query cost limit.
        foreach (array_chunk($metafields, 10) as $metafieldBatch) {
            $productBatchSize = min(250, max(1, intdiv(500, count($metafieldBatch))));
            foreach (array_chunk($productIds, $productBatchSize) as $productIdBatch) {
                $nodes = $this->graphqlNodes(
                    $this->requestAdminProductMetafields($productIdBatch, $metafieldBatch),
                    'product metafields'
                );

                foreach ($nodes as $node) {
                    if (! is_array($node)) {
                        continue;
                    }

                    $productId = strval(A::get($node, 'legacyResourceId', ''));
                    if ($productId === '') {
                        continue;
                    }

                    foreach ($metafieldBatch as $index => $identifier) {
                        $metafield = A::get($node, 'metafield'.$index);
                        if (! is_array($metafield)) {
                            continue;
                        }

                        $namespace = strval(A::get($metafield, 'namespace', $identifier['namespace']));
                        $key = strval(A::get($metafield, 'key', $identifier['key']));
                        $metafieldsByProduct[$productId][$namespace][$key] = $metafield;

                        $referenceIds = $this->metaobjectReferenceIds($metafield);
                        $referenceIdsByMetafield[$productId][$namespace][$key] = $referenceIds;
                        foreach ($referenceIds as $referenceId) {
                            $metaobjectIds[$referenceId] = $referenceId;
                        }
                    }
                }
            }
        }

        $metaobjects = [];
        foreach (array_chunk(array_values($metaobjectIds), 249) as $metaobjectIdBatch) {
            $nodes = $this->graphqlNodes(
                $this->requestAdminMetaobjects($metaobjectIdBatch),
                'metaobjects'
            );
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }

                $metaobjectId = strval(A::get($node, 'id', ''));
                if ($metaobjectId !== '') {
                    $metaobjects[$metaobjectId] = $node;
                }
            }
        }

        foreach ($referenceIdsByMetafield as $productId => $namespaces) {
            foreach ($namespaces as $namespace => $keys) {
                foreach ($keys as $key => $referenceIds) {
                    $references = [];
                    foreach ($referenceIds as $referenceId) {
                        if (isset($metaobjects[$referenceId])) {
                            $references[] = $metaobjects[$referenceId];
                        }
                    }

                    $type = $metafieldsByProduct[$productId][$namespace][$key]['type'] ?? null;
                    if ($type === 'metaobject_reference') {
                        if (isset($references[0])) {
                            $metafieldsByProduct[$productId][$namespace][$key]['reference'] = $references[0];
                        }
                    } elseif ($type === 'list.metaobject_reference') {
                        $metafieldsByProduct[$productId][$namespace][$key]['references'] = $references;
                    }
                }
            }
        }

        foreach ($products as &$product) {
            $productId = strval(A::get($product, 'id', ''));
            $product['metafields'] = $metafieldsByProduct[$productId] ?? [];
        }
        unset($product);

        return $products;
    }

    private function graphqlNodes(Remote $remote, string $resource): array
    {
        $json = $remote->json();
        if ($remote->code() !== 200) {
            throw new \RuntimeException('Shopify Admin '.$resource.' request failed with status '.$remote->code());
        }
        if (! is_array($json)) {
            throw new \RuntimeException('Shopify Admin '.$resource.' request did not return JSON');
        }

        $errors = A::get($json, 'errors', []);
        if (is_array($errors) && $errors !== []) {
            $messages = array_map(
                fn ($error) => is_array($error) ? strval(A::get($error, 'message', 'Unknown error')) : strval($error),
                $errors
            );

            throw new \RuntimeException('Shopify Admin '.$resource.' request failed: '.implode('; ', $messages));
        }

        $nodes = A::get($json, 'data.nodes');
        if (! is_array($nodes)) {
            throw new \RuntimeException('Shopify Admin '.$resource.' request returned no nodes');
        }

        return $nodes;
    }

    private function metaobjectReferenceIds(array $metafield): array
    {
        $type = A::get($metafield, 'type');
        $value = A::get($metafield, 'value');
        if (! is_string($value) || $value === '') {
            return [];
        }

        if ($type === 'metaobject_reference') {
            return [$value];
        }
        if ($type !== 'list.metaobject_reference') {
            return [];
        }

        $values = json_decode($value, true);
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, fn ($value) => is_string($value) && $value !== ''));
    }

    private function productMetafields(): array
    {
        $configured = $this->option('metafields');
        if ($configured === null || $configured === false || $configured === []) {
            return [];
        }
        if (! is_array($configured)) {
            throw new \InvalidArgumentException('Shopify metafields must be configured as an array of namespace.key strings');
        }

        $metafields = [];
        foreach ($configured as $configuredMetafield) {
            if (! is_string($configuredMetafield) || ! str_contains($configuredMetafield, '.')) {
                throw new \InvalidArgumentException('Shopify metafields must be configured as namespace.key strings');
            }

            [$namespace, $key] = array_map('trim', explode('.', $configuredMetafield, 2));
            if ($namespace === '' || $key === '') {
                throw new \InvalidArgumentException('Shopify metafields must be configured as namespace.key strings');
            }

            $metafields[$namespace.'.'.$key] = [
                'namespace' => $namespace,
                'key' => $key,
            ];
        }

        return array_values($metafields);
    }

    protected function requestAdminProducts(?string $pageInfo): Remote
    {
        return Remote::get($this->adminEndpoint().'/products.json', [
            'headers' => $this->adminHeaders(),
            'data' => array_filter([
                'limit' => 250,
                'page_info' => $pageInfo,
                'fields' => 'id,title,body_html,tags,images,options,variants',
            ]),
        ]);
    }

    protected function requestAdminProductMetafields(array $productIds, array $metafields): Remote
    {
        $selections = [];
        foreach ($metafields as $index => $metafield) {
            $namespace = json_encode($metafield['namespace'], JSON_THROW_ON_ERROR);
            $key = json_encode($metafield['key'], JSON_THROW_ON_ERROR);
            $selections[] = "metafield{$index}: metafield(namespace: {$namespace}, key: {$key}) {\n"
                .'        namespace key type value'."\n"
                .'      }';
        }

        $query = str_replace('__METAFIELDS__', implode("\n      ", $selections), <<<'GQL'
query ProductMetafields($ids: [ID!]!) {
  nodes(ids: $ids) {
    ... on Product {
      legacyResourceId
      __METAFIELDS__
    }
  }
}
GQL);

        return Remote::post($this->adminEndpoint().'/graphql.json', [
            'headers' => array_merge($this->adminHeaders(), [
                'Content-Type' => 'application/json',
            ]),
            'data' => json_encode([
                'query' => $query,
                'variables' => [
                    'ids' => array_map(
                        fn (string $productId) => str_contains($productId, 'gid://')
                            ? $productId
                            : 'gid://shopify/Product/'.$productId,
                        $productIds
                    ),
                ],
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    protected function requestAdminMetaobjects(array $metaobjectIds): Remote
    {
        return Remote::post($this->adminEndpoint().'/graphql.json', [
            'headers' => array_merge($this->adminHeaders(), [
                'Content-Type' => 'application/json',
            ]),
            'data' => json_encode([
                'query' => <<<'GQL'
query Metaobjects($ids: [ID!]!) {
  nodes(ids: $ids) {
    ... on Metaobject {
      id
      type
      handle
      displayName
      fields {
        key
        type
        value
      }
    }
  }
}
GQL,
                'variables' => [
                    'ids' => $metaobjectIds,
                ],
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    private function adminEndpoint(): string
    {
        $version = $this->apiVersion();
        $domain = $this->storeDomain();
        if ($domain === '') {
            throw new \RuntimeException('Shopify store_domain is required to fetch products');
        }

        return 'https://'.$domain.'/admin/api/'.$version;
    }

    private function adminHeaders(): array
    {
        return [
            'X-Shopify-Access-Token' => $this->adminAccessToken(),
            'Accept' => 'application/json',
        ];
    }

    private function adminAccessToken(): string
    {
        $clientId = trim(strval($this->option('client_id')));
        $clientSecret = trim(strval($this->option('client_secret')));

        if ($clientId === '' || $clientSecret === '') {
            throw new \RuntimeException('Shopify client_id and client_secret are required to request an Admin API token');
        }

        return $this->requestAdminAccessToken($clientId, $clientSecret);
    }

    protected function requestAdminAccessToken(string $clientId, string $clientSecret): string
    {
        // https://shopify.dev/docs/apps/build/authentication-authorization/client-secrets
        $remote = Remote::post($this->adminAccessTokenEndpoint(), [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
            'data' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
            ],
        ]);

        $json = $remote->json();
        $token = $remote->code() === 200 && is_array($json)
            ? A::get($json, 'access_token')
            : null;
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $error = is_array($json)
            ? A::get($json, 'error_description', A::get($json, 'error'))
            : null;
        $message = 'Shopify Admin API token request failed with status '.$remote->code();
        if (is_string($error) && $error !== '') {
            $message .= ': '.$error;
        }

        throw new \RuntimeException($message);
    }

    protected function adminAccessTokenEndpoint(): string
    {
        $domain = $this->storeDomain();
        if ($domain === '') {
            throw new \RuntimeException('Shopify store_domain is required to request an Admin API token');
        }

        return 'https://'.$domain.'/admin/oauth/access_token';
    }

    private function storefrontEndpoint(): string
    {
        $version = $this->apiVersion();
        $domain = $this->storeDomain();
        if ($domain === '') {
            throw new \RuntimeException('Shopify store_domain is required to create a checkout');
        }

        return 'https://'.$domain.'/api/'.$version.'/graphql.json';
    }

    private function apiVersion(): string
    {
        $version = trim(strval($this->option('api_version') ?? ''));

        return $version !== '' ? $version : self::API_VERSION;
    }

    private function storefrontHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Shopify-Storefront-Private-Token' => strval($this->option('storefront_token')),
        ];
    }

    private function variantGid(string $variantId): string
    {
        return str_contains($variantId, 'gid://') ? $variantId : 'gid://shopify/ProductVariant/'.$variantId;
    }

    private function canFetchProducts(): bool
    {
        if ($this->storeDomain() === '') {
            return false;
        }

        $clientId = trim(strval($this->option('client_id')));
        $clientSecret = trim(strval($this->option('client_secret')));

        return $clientId !== '' && $clientSecret !== '';
    }

    private function storeDomain(): string
    {
        $domain = trim(strval($this->option('store_domain')));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = explode('/', $domain)[0];

        return rtrim($domain, '/');
    }
}
