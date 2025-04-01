<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart\Mixins;

use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\VirtualPage;
use Kirby\Data\Yaml;

trait TMNT
{
    public function tmnt(): void
    {
        kirby()->impersonate('kirby', function (): void {
            // create generic products for the demo
            $prods = $this->page(ContentPageEnum::PRODUCTS);
            $stocks = $this->page(ContentPageEnum::STOCKS);
            if (! kirby()->environment()->isLocal() || ! $prods || $prods->children()->count() !== 0) {
                return;
            }

            $turtles = [
                ['content' => ['title' => 'Leonardo', 'price' => 20, 'categories' => 'heroes,ninja', 'tags' => 'mutant,leader,blue,turtle,katana']],
                ['content' => ['title' => 'Raphael', 'price' => 20, 'categories' => 'heroes,ninja', 'tags' => 'mutant,red,turtle,sai']],
                ['content' => ['title' => 'Donatello', 'price' => 20, 'categories' => 'heroes,ninja', 'tags' => 'mutant,purple,turtle,bo']],
                ['content' => ['title' => 'Michelangelo', 'price' => 20, 'categories' => 'heroes,ninja', 'tags' => 'mutant,orange,turtle,nunchucks']],
                ['content' => ['title' => 'Splinter', 'price' => 15, 'categories' => 'heroes,ninja', 'tags' => 'mutant,sensei,rat,staff']],
                ['content' => ['title' => 'April O\'Neil', 'price' => 15, 'categories' => 'heroes', 'tags' => 'human']],
                ['content' => ['title' => 'Shredder', 'price' => 20, 'categories' => 'villains,super-villains,ninja', 'tags' => 'human,claws']],
                ['content' => ['title' => 'Foot Clan', 'price' => 5, 'categories' => 'villains,ninja', 'tags' => 'human,katana,sai,bo,nunchucks']],
                ['content' => ['title' => 'Karai', 'price' => 15, 'categories' => 'villains,ninja', 'tags' => 'human,leader,katana']],
                ['content' => ['title' => 'Krang', 'price' => 15, 'categories' => 'villains,super-villains', 'tags' => 'alien']],
                ['content' => ['title' => 'Bebop', 'price' => 10, 'categories' => 'villains', 'tags' => 'mutant,warthog']],
                ['content' => ['title' => 'Rocksteady', 'price' => 10, 'categories' => 'villains', 'tags' => 'mutant,rhinoceros']],
            ];

            foreach ($turtles as $turtle) {
                $product = $prods->createChild(
                    (new VirtualPage($turtle, [
                        'title' => 'content.title',
                        'content' => 'content',
                    ]))
                        ->mixinProduct()
                        ->toArray()
                );
                $stocks?->createChild([
                    'title' => $product->title(), // will be replaced by StockPage::create
                    'template' => 'stock',
                    'content' => [
                        'page' => Yaml::encode([$product->uuid()->toString()]),
                        'stock' => random_int(0, 10),
                    ],
                ]);
            }
        });
    }
}
