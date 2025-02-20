<?php

namespace Bnomei\Kart\Mixins;

use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\Str;

trait ContentPages
{
    public function makeContentPages(App $kirby): void
    {
        $pages = array_filter([
            'orders' => $kirby->option('bnomei.kart.orders.enabled') === true ? $kirby->option('bnomei.kart.orders.model') : null,
            'products' => $kirby->option('bnomei.kart.products.enabled') === true ? $kirby->option('bnomei.kart.products.model') : null,
            'stocks' => $kirby->option('bnomei.kart.stocks.enabled') === true ? $kirby->option('bnomei.kart.stocks.model') : null,
        ]);

        $kirby->impersonate('kirby', function () use ($kirby, $pages) {
            foreach ($pages as $key => $class) {
                if (! $this->page($key)) {
                    $title = str_replace('Page', '', $class);
                    $page = site()->createChild([
                        'id' => $kirby->option("bnomei.kart.{$key}.page"),
                        'template' => Str::lower($title),
                        'model' => $class,
                        'content' => [
                            'title' => $title,
                            'uuid' => $key, // must match key to make them easier to find by kart
                        ],
                    ]);
                    // force unlisted
                    Dir::move($page->root(), str_replace('_drafts/', '', $page->root()));
                }
            }
        });

    }
}
