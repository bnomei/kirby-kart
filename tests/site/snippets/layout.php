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
            --color-gcpurple: #7157FF;
            --color-gcyellow: #FADB01;
            --color-gcred: #F23943;
            --color-gcgreen: #1ABA7D;
            --color-gcwhite: #D8DADC;
            --color-gcgray: #9FA1A2;
            --color-gcblack: #242627;
        }
    </style>
</head>
<body class="max-w-screen-lg mx-auto text-gcblack bg-gray-50">
    <section class="min-h-screen bg-white">
        <header class="bg-kart text-white border-b-2 border-gray-50 px-6 py-4">
            <nav class="flex justify-between">
                <ul class="flex items-baseline pb-1">
                <?php $c = 0;
foreach (site()->breadcrumb() as $crumb) { ?>
                    <li><a class="hover:underline <?= $crumb->isActive() ? 'underline' : '' ?> <?= $c == 0 ? 'text-2xl' : '' ?>" href="<?= $crumb->url() ?>"><?= $crumb->title() ?></a></li>
                    <li class="last:hidden px-2">»</li>
                <?php $c++;
} ?>
                </ul>
                <div class="flex items-center pr-4">
                    <a href="/cart" class="text-xs px-2 py-1 bg-kart text-white border-1 border-white hover:bg-white hover:text-kart rounded-md">Cart <small>(<?= kart()->cart()->quantity() ?>)</small></a>
                </div>
            </nav>
        </header>
        <main class="px-6 py-6">
            <?= $slot ?>
        </main>
    </section>
</body>
</html>
