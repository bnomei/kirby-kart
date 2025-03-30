<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */
use Bnomei\Kart\VirtualPage;

beforeEach(function () {
    $this->map = [
        'title' => 'content.title',
        'content' => 'content',
    ];
});

it('has a virtual page', function () {
    expect(VirtualPage::class)->toBeString();
});

it('can instantiate VirtualPage with dummy properties', function () {
    $v = new VirtualPage([
        'content' => [
            'title' => 'Dummy Title',
            'dummy' => 'Dummy Content',
            'arr' => [
                'a' => 1,
            ],
        ],
    ], $this->map);

    expect($v)->toBeInstanceOf(VirtualPage::class)
        ->and($v->title)->toBe('Dummy Title')
        ->and($v->title(null))->toBeInstanceOf(VirtualPage::class)
        ->and($v->content)->toBe([
            'arr' => ['a' => 1],
            'dummy' => 'Dummy Content',
            'raw' => [],
            'title' => 'Dummy Title',
        ])->and($v->toArray())->toBe([
            'num' => null,
            'content' => [
                'arr' => \Kirby\Data\Yaml::encode(['a' => 1]),
                'dummy' => 'Dummy Content',
                'title' => 'Dummy Title',
            ],
            'template' => 'default',
            'model' => 'default',
            'slug' => 'dummy-title',
            'id' => 'dummy-title',
        ]);

    $v->mixinProduct([
        'url' => 'https://example.com/product/123',
    ]);
    expect($v->raw['url'])->toBe('https://example.com/product/123')
        ->and($v->template)->toBe('product')
        ->and($v->model)->toBe('product');
});

it('can have a parent to infer its full id', function () {
    $parent = 'par/ent';
    $v = new VirtualPage([
        'content' => [
            'title' => 'Dummy Title',
            'dummy' => 'Dummy Content',
        ],
    ], $this->map, $parent);

    expect($v->parent)->toBe($parent)
        ->and($v->slug)->toBe('dummy-title')
        ->and($v->id)->toBe('par/ent/dummy-title');
});

it('can handle more complex mappings', function () {
    $v = new VirtualPage([
        'content' => [
            'title' => 'Dummy Title',
            'a' => 1,
            'b1' => 2,
            'b2' => 3,
        ],
    ], [
        'title' => 'content.title',
        'a' => fn ($i) => $i['content']['a'] * 2,
        'b' => [
            'content.b1',
            'content.b2',
        ],
    ]);
    expect($v->title)->toBe('Dummy Title')
        ->and($v->a)->toBe(2)
        ->and($v->b)->toBe([2, 3])
        ->and($v->c)->toBeNull();
});
