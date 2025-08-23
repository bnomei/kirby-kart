<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use AllowDynamicProperties;
use Closure;
use Kirby\Data\Yaml;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Obj;
use Kirby\Toolkit\Str;

/**
 * Kart Virtual Page is used to create virtual (product) pages from synced data
 *
 * @property string $id
 * @property string $uuid
 * @property string $title
 * @property string $slug
 * @property ?string $model
 * @property string $template
 * @property ?int $num
 * @property array $content
 * @property array $raw
 *
 * @method self id(string $id)
 * @method self uuid(string $uuid)
 * @method self title(string $title)
 * @method self slug(string $slug)
 * @method self model(?string $model)
 * @method self template(?string $template)
 * @method self num(?int $num)
 * @method self content(array $content)
 * @method self raw(array $raw)
 */
#[AllowDynamicProperties]
class VirtualPage extends Obj
{
    public function __construct(
        array $data = [],
        array $map = [],
        public ?string $parent = null,
    ) {
        // defaults
        parent::__construct([
            'num' => null,
        ]);
        $this->content([]);
        $this->raw([]);
        $this->template('default'); // => model
        // $this->uuid(Uuid::generate()); // handled externally

        // load
        foreach ($map as $property => $path) {
            $this->$property($this->resolveMap($data, $path));
        }
    }

    private function resolveMap(array $data, string|array|Closure $path): mixed
    {
        if (is_string($path)) {
            return A::get($data, $path); // dot-notion support
        } elseif (is_array($path)) {
            $out = [];
            foreach ($path as $key => $value) {
                $out[$key] = $this->resolveMap($data, $value);
            }

            return $out;
        } elseif ($path instanceof Closure) {
            return $path($data);
        }
    }

    public function __get(string $property): mixed
    {
        if (in_array($property, ['uuid', 'title', 'raw'])) {
            return A::get($this->get('content'), $property);
        }

        return $this->get($property);
    }

    public function __call(string $property, array $arguments): self
    {
        $value = $arguments[0] ?? null;

        // do not set on null to allow config with null value to keep current value
        if (is_null($value)) {
            return $this;
        }

        // move some into content instead
        if (in_array($property, ['uuid', 'title', 'raw'])) {
            $this->content([$property => $value]);
        } elseif ($property === 'content') {
            $this->content = array_merge(
                $this->content ?? [],
                $value
            );
            ksort($this->content);
        } else {
            $this->$property = $value;
        }

        // infer slug from title
        if ($property === 'title' && ! $this->get('slug')) {
            $this->slug(Str::slug(strval($value)));
        }

        // infer model from template
        if ($property === 'template' && ! $this->get('model')) {
            $this->model($value);
        }

        // infer id from parent and slug
        if ($property === 'slug' && ! $this->get('id')) {
            $this->id($this->parent ?
                $this->parent.'/'.$this->slug :
                $this->slug
            );
        }

        return $this;
    }

    public function toArray(): array
    {
        $result = parent::toArray();

        // remove all null values in content so when hydration with
        // local file happens the local value will be kept
        $result['content'] = array_filter($result['content']);

        // convert all arrays in content to yaml
        foreach ($result['content'] as $key => $value) {
            if (is_array($value)) {
                $result['content'][$key] = Yaml::encode($value);
            }
        }

        ksort($result['content']);

        unset($result['parent']); // do not expose the parent

        return $result;
    }

    public function mixinProduct(array $data = []): self
    {
        // set template and model for products
        $this->template('product');
        $this->model('product');

        // store raw data as yaml encoded array
        $deepKsort = function (&$a) use (&$deepKsort) {
            if (is_array($a)) {
                ksort($a);
                array_walk($a, fn (&$v) => $deepKsort($v));
            }
        };
        $deepKsort($data);
        $this->raw($data);

        return $this;
    }
}
