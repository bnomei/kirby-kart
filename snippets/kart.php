<?php
// KART localhost dev demo should not be used online
if (! kirby()->environment()->isLocal()) {
    go('/', 418);
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= $page->title() ?></title>
</head>
<body>
    <nav>
        <ul>
            <?php foreach (site()->breadcrumb() as $crumb) { ?>
                <li><a href="<?= $crumb->url() ?>"><?= $crumb->title() ?></a></li>
            <?php } ?>
            <li><a href="<?= url(\Bnomei\Kart\Router::CART) ?>">Cart (<?= kart()->cart()->quantity() ?>)</a></li>
        </ul>
        <hr>
    </nav>

    <!-- SLOT: default -->
    <?= $slots->default() ?>
    <!-- /SLOT: default -->
</body>
</html>
