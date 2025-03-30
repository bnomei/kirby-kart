<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Queue;
use Bnomei\Kart\Ratelimit;
use Bnomei\Kart\Router;
use Bnomei\Kart\Tag;
use Bnomei\Kart\Wishlist;
use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;

beforeEach(function () {
    $this->q = new Queue;
    $this->q->flush();
});

afterEach(function () {
    $this->q->flush();
});

it('can push and remove a job', function () {
    $this->q->push(['foo' => 'bar']);
    $key = $this->q->push(['foo' => 'bar']);
    $this->q->push(['foo2' => 'bar2']);

    expect($this->q->count())->toBe(3);

    $this->q->remove($key);
    expect($this->q->count())->toBe(2);
});

it('can have failed jobs', function () {
    $this->q->push(['noknowtype']);
    $this->q->process();
    expect($this->q->count())->toBe(0)
        ->and($this->q->count(true))->toBe(1);
});

it('can handle having no dir, having 0 jobs', function () {
    $this->q->push([]);
    $this->q->flush();
    Dir::remove(kirby()->cache('bnomei.kart.queue')->root());
    $this->q->process();
    expect($this->q->count())->toBe(0);
});

it('can handle broken jobs', function () {
    $dir = kirby()->cache('bnomei.kart.queue')->root();
    $broken = $this->q->push([
        'page' => 'home',
        'method' => 'url',
    ]);
    F::write($dir.'/'.$broken.'.json', '');

    $this->q->process();
    expect($this->q->count())->toBe(0)
        ->and($this->q->count(true))->toBe(1);
});

it('can handle locked jobs', function () {
    $dir = kirby()->cache('bnomei.kart.queue')->root();
    $locked = $this->q->push([
        'page' => 'home',
        'method' => 'slug',
    ]);
    $file = $dir.'/'.$locked.'.json';
    $fileHandle = fopen($file, 'r');
    flock($fileHandle, LOCK_EX | LOCK_NB);

    expect($this->q->process())->toBeNull(); // aborting because of lock
    flock($fileHandle, LOCK_UN);
});

it('will not process the queue when a lock exists', function () {
    $this->q->push(['foo' => 'bar']);

    expect($this->q->process(unlock: false))->toBe(1)
        ->and($this->q->process())->toBeNull();
});

it('can handle jobs on pages', function () {
    $this->q->push([
        'page' => 'home',
        'method' => 'id',
    ]);
    $this->q->push([
        'page' => 'home',
        'method' => 'changeStatus',
        'data' => ['listed'],
    ]);
    $this->q->push([
        'page' => 'home',
        'method' => 'noDynamicFields',
    ]);
    $this->q->process();
    expect($this->q->count())->toBe(0)
        ->and($this->q->count(true))->toBe(2); // because of missing impersonate
});

it('can handle jobs on classes', function () {
    $this->q->push([
        'class' => 'doesnotexist',
        'method' => '::version',
    ]);
    $this->q->push([
        'class' => App::class,
        'method' => '::version',
    ]);
    $this->q->push([
        'class' => Ratelimit::class,
        'method' => '::check',
        'data' => ['1.1.1.1'],
    ]);
    $this->q->push([
        'class' => Router::class,
        'method' => 'csrf',
    ]);
    $this->q->push([
        'class' => Tag::class,
        'props' => [[
            'id' => 'test',
            'url' => 'https://example.com/',
        ]],
        'method' => 'url',
    ]);
    $this->q->push([
        'class' => Wishlist::class,
        'method' => 'add',
        'data' => [
            null, // no product but that is allowed
        ],
    ]);
    $this->q->push([ // fails because the queue is locked while processing
        'page' => Queue::class,
        'method' => 'push',
        'data' => [
            [
                'foo' => 'bar',
            ],
        ],
    ]);
    $this->q->process();
    expect($this->q->count())->toBe(0)
        ->and($this->q->count(true))->toBe(2); // check() and url('test') will fail
});

it('can work without the locking (not recommended)', function () {
    kart()->setOption('queues.locking', false);
    $this->q->push([
        'page' => 'home',
        'method' => 'id',
    ]);
    expect($this->q->process())->toBe(1);
});
