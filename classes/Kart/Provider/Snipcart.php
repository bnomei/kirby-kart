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
use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;
use Bnomei\Kart\Router;
use Bnomei\Kart\VirtualPage;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;
use Kirby\Uuid\Uuid;

class Snipcart extends Provider
{
    protected string $name = ProviderEnum::SNIPCART->value;

    private function headers(): array
    {
        // https://docs.snipcart.com/v3/api-reference/authentication
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic '.base64_encode(strval($this->option('secret_key')).':'),
        ];
    }

    public function checkout(): ?string
    {
        $session_id = Uuid::generate();
        $this->kirby->session()->set('bnomei.kart.'.$this->name.'.session_id', $session_id);

        return parent::checkout() ? Router::provider_payment([
            'session_id' => $session_id,
        ]) : '/';
    }

    public function fetchProducts(): array
    {
        $products = [];
        $offset = 0;
        $limit = 50;

        while (true) {
            // https://docs.snipcart.com/v3/api-reference/products
            $remote = Remote::get("https://app.snipcart.com/api/products?limit=$limit&offset=$offset", [
                'headers' => $this->headers(),
            ]);

            $json = $remote->code() === 200 ? $remote->json() : null;
            if (! is_array($json)) {
                break;
            }

            foreach (A::get($json, 'items') as $product) {
                // keep active products; skip archived ones
                if (A::get($product, 'archived')) {
                    continue;
                }
                $products[$product['id']] = $product;
            }

            if (count($products) >= intval(A::get($json, 'totalItems', 0))) {
                break;
            }
            $offset += $limit;
        }

        return array_map(fn (array $data) => // NOTE: changes here require a cache flush to take effect
        (new VirtualPage(
            $data,
            [
                // MAP: kirby <=> snipcart
                'id' => 'id', // id, uuid and slug will be hashed in ProductPage::create based on this `id`
                'title' => 'name',
                'content' => [
                    'created' => fn ($i) => date('Y-m-d H:i:s', strtotime($i['creationDate'])),
                    'description' => 'description',
                    'price' => fn ($i) => A::get($i, 'price', 0),
                    'tags' => fn ($i) => A::get($i, 'metadata.tags', A::get($i, 'metadata.tag', '')),
                    'categories' => fn ($i) => A::get($i, 'metadata.categories', A::get($i, 'metadata.category', '')),
                    'gallery' => fn ($i) => $this->findImagesFromUrls(
                        explode(',', A::get($i, 'image_url', A::get($i, 'custom_data.gallery', '')))
                    ),
                    'downloads' => fn ($i) => $this->findFilesFromUrls(
                        explode(',', A::get($i, 'metadata.downloads', ''))
                    ),
                ],
            ],
            $this->kart->page(ContentPageEnum::PRODUCTS))
        )->mixinProduct($data)->toArray(), $products);
    }
}
