<?php

namespace Bnomei\Kart\Mixins;

use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\Str;
use OrdersPage;
use ProductsPage;
use StocksPage;

trait ContentPages
{
    public function makeContentPages(App $kirby): void
    {
        $pages = array_filter([
            'orders' => $kirby->option('bnomei.kart.orders.enabled') === true ? OrdersPage::class : null,
            'products' => ProductsPage::class,
            'stocks' => $kirby->option('bnomei.kart.stocks.enabled') === true ? StocksPage::class : null,
        ]);

        $kirby->impersonate('kirby', function () use ($kirby, $pages) {
            foreach ($pages as $key => $class) {
                if (! $this->page($key)) {
                    $title = str_replace('Page', '', $class);
                    $page = site()->createChild([
                        'id' => $kirby->option("bnomei.kart.{$key}.page"),
                        'template' => Str::lower($title),
                        'content' => [
                            'title' => $title,
                            'uuid' => Str::lower($title),
                        ],
                    ]);
                    // force unlisted
                    Dir::move($page->root(), str_replace('_drafts/', '', $page->root()));
                }
            }
        });

    }
}
