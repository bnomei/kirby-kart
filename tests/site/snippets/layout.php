<?php
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-eval' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; style-src 'self' 'unsafe-inline'; img-src 'self' https://www.gravatar.com;");
?><!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $page->title() ?> | <?= site()->title() ?></title>

    <?= match (option('tests.frontend')) {
        'kart/datastar' => '<script type="module" src="https://cdn.jsdelivr.net/gh/starfederation/datastar@v1.0.0-beta.7/bundles/datastar.js"></script>',
        'kart/htmx' => '<script type="module" src="https://unpkg.com/htmx.org@1/dist/htmx.min.js"></script>',
        'kart/html' => '<script>/* plain HTML forms */</script>',
    } ?>

    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <style type="text/tailwindcss">
        @theme {
            --color-kart: #7157FF;
        }
    </style>
</head>
<body class="max-w-screen-lg mx-auto bg-gray-100">
    <section class="min-h-screen bg-white">
        <header class="border-b-2 border-gray-100 px-6 py-4">
            <nav class="flex justify-between">
                <ul class="text-kart flex">
                <?php foreach (site()->breadcrumb() as $crumb) { ?>
                    <li><a class="hover:underline <?= $crumb->isActive() ? 'font-bold' : '' ?>" href="<?= $crumb->url() ?>"><?= $crumb->title() ?></a></li>
                    <li class="last:hidden px-1">»</li>
                <?php } ?>
                </ul>
                <div>
                    <a href="/cart" class="group font-bold"><span class="group-hover:underline ">Cart</span> <small>(<?= kart()->cart()->lines()->count() ?>)</small></a>
                </div>
            </nav>
        </header>
        <main class="px-6 py-4">
            <?= $slot ?>
        </main>
    </section>
</body>
</html>
