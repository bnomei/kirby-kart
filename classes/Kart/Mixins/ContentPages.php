<?php

namespace Bnomei\Kart\Mixins;

use Bnomei\Kart\ContentPageEnum;
use Kirby\Cms\Page;

trait ContentPages
{
    public function makeContentPages(): void
    {
        $pages = [];
        foreach (ContentPageEnum::cases() as $enum) {
            $pages[$enum->value] = $this->kirby->option("bnomei.kart.{$enum->value}.enabled") ?
                $this->kirby->option("bnomei.kart.{$enum->value}.page") : null;
        }
        $pages = array_filter($pages, fn ($id) => ! empty($id) && $this->page($id) === null);

        $this->kirby->impersonate('kirby', function () use ($pages) {
            foreach ($pages as $key => $id) {
                Page::create([
                    'id' => $id,
                    'isDraft' => false,
                    'template' => $this->kirby->option("bnomei.kart.{$key}.template", $key),
                    'model' => $this->kirby->option("bnomei.kart.{$key}.model", $key),
                    'content' => [
                        'title' => t("kart.{$key}", ucfirst($key)),
                        'uuid' => $key, // match key to make them easier to find
                    ],
                ]);
            }
        });
    }
}
