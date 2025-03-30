<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */
use Bnomei\Kart\Category;

it('has a category helper', function () {
    $c = new Category([
        'id' => 'id',
        'text' => 'text',
        'count' => 5,
        'value' => 'value',
        'isActive' => false,
        'url' => 'url',
        'urlWithParams' => 'urlWithParams',
    ]);
    expect($c)->toBeInstanceOf(Category::class)
        ->and($c->toArray())->toBeArray()
        ->and($c->id())->toBe('id')
        ->and($c->text())->toBe('text')
        ->and($c->count())->toBe(5)
        ->and($c->value())->toBe('value')
        ->and($c->isActive())->toBeFalse()
        ->and($c->url())->toBe('url')
        ->and($c->urlWithParams())->toBe('urlWithParams')
        ->and($c->nope())->toBe(null);
});
