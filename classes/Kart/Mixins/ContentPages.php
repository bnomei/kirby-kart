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
        $pages = array_filter($pages, fn ($id) => ! empty($id) && is_string($id) && $this->kirby()->page($id) === null);

        if (! $this->kirby->environment()->isLocal() && $this->kirby->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
            $pages = [];
        }

        $this->kirby->impersonate('kirby', function () use ($pages) {
            foreach ($pages as $key => $id) {
                $props = [
                    'id' => $id,
                    'isDraft' => false,
                    'template' => $this->kirby->option("bnomei.kart.{$key}.template", $key),
                    'model' => $this->kirby->option("bnomei.kart.{$key}.model", $key),
                ];
                if (kirby()->multilang()) {
                    foreach (kirby()->languages() as $language) {
                        $languageCode = $language->code();
                        $props['translations'] = [
                            $languageCode => [
                                'code' => $languageCode,
                                'content' => array_filter([
                                    'title' => t("bnomei.kart.{$key}", ucfirst($key), $languageCode),
                                    'uuid' => $languageCode === kirby()->defaultLanguage()?->code() ? $key : null, // match key to make them easier to find
                                ]),
                            ],
                        ];
                    }
                } else {
                    $props['content'] = [
                        'title' => t("bnomei.kart.{$key}", ucfirst($key)),
                        'uuid' => $key, // match key to make them easier to find
                    ];
                }
                Page::create($props);
            }
        });
    }
}
