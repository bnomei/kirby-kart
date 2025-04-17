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
use Kirby\Toolkit\A;

$page ??= kirby()->site()->page();
$template ??= $page->intendedTemplate();
$props ??= $page->toKerbs();
if ($props instanceof \Kirby\Content\Field) {
    $props = [];
}
$request = kirby()->request();

$inertia = array_filter([
    'component' => ucfirst($template->name()),
    'props' => array_map(fn($value) => $value instanceof Closure ? $value() : $value, $props + kart()->option('kerbs.shared')),
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
        'X-Inertia' => 'true'
    ]);
    exit();
}

// otherwise render the app
?><!DOCTYPE html>
<html lang="<?= kirby()->language()?->code() ?? 'en' ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= page()->isHomePage() ? site()->title() : page()->title().' | '.site()->title() ?></title>
</head>
<body>
    <!-- Kart Kerbs -->
    <div id="<?= $appId ?? 'app' ?>" data-page='<?= json_encode($inertia) ?>'></div>
    <script src="<?= kirby()->urls()->media() ?>/plugins/bnomei/kart/kerbs.iife.js"></script>
</body>
</html>
