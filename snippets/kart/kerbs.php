<?php

use Kirby\Cms\Response;

$page ??= kirby()->site()->page();
$template ??= $page->intendedTemplate();
$props ??= $page->toKerbs();
$request = kirby()->request();

// TODO: partials
// TODO: (maybe) shared global props

$inertia = array_filter([
    'component' => ucfirst($template->name()),
    'props' => array_map(fn($value) => $value instanceof Closure ? $value() : $value, $props + option('bnomei.kart.kerbs.shared', [])),
    'url' => $request->url()->toString(),
    'version' => option('bnomei.kart.kerbs.version'), // TODO: read manifest file if it exists
]);

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
<html lang="en">
<head>
    <title><?= site()->title() ?></title>
</head>
<body>
    <!-- Kart Kerbs -->
    <div id="<?= $id ?? 'app' ?>" data-page='<?= json_encode($inertia) ?>'></div>
    <script src="<?= kirby()->urls()->media() ?>/plugins/bnomei/kart/kerbs.iife.js"></script>
</body>
</html>
