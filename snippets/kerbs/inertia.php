<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 *
 * Inertia Adapter is based on code by
 * Copyright (c) 2020 Jon Gacnik
 * https://github.com/monoeq/kirby-inertia
 * (MIT license)
 */

use Kirby\Cms\Response;
use Kirby\Content\Field;
use Kirby\Toolkit\A;

$page ??= kirby()->site()->page();
$template ??= $page->intendedTemplate();
$props ??= ['page' => $page->toKerbs()];
if ($props instanceof Field) {
    $props = [];
}
$request = kirby()->request();

$inertia = array_filter([
    'component' => ucfirst($template->name()),
    'props' => array_map(fn ($value) => $value instanceof Closure ? $value() : $value, $props + kart()->option('kerbs.shared')),
    'url' => $request->url()->toString(),
    'version' => kart()->option('kerbs.version'),
]);

// only return partial props when requested
$only = array_filter(explode(',', $request->header('X-Inertia-Partial-Data') ?? ''));
$inertia['props'] = ($only && $request->header('X-Inertia-Partial-Component') === $inertia['component'])
    ? A::get($inertia['props'], $only)
    : $inertia['props'];

// return json when in inertia mode
if ($request->method() === 'GET' && $request->header('X-Inertia')) {
    echo Response::json($inertia, headers: [
        'Vary' => 'Accept',
        'X-Inertia' => 'true',
    ]);
    exit();
}

// otherwise render the app
snippet('kerbs/layout', slots: true);
?>
    <!-- Kirby Kart Plugin, Kerbs Theme: a Svelte 5 frontend with Inertia.js Adapter for Kirby CMS -->
    <main class="container" id="<?= $appId ?? 'app' ?>" data-page='<?= json_encode($inertia) ?>'></main>
    <script defer src="<?= kirby()->urls()->media() ?>/plugins/bnomei/kart/kerbs.iife.js"></script>

<?php endsnippet();
