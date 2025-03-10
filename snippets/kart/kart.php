<?php
// KART localhost dev demo should not be used online
if (! kirby()->environment()->isLocal()) {
    // go('/', 418);
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= $page->title() ?></title>

    <style>
        nav {
            padding: 1rem 0;
        }
        .is-active {
            font-weight: bold;
        }
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            grid-gap: 1rem;
        }
        .card {
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 10px;
            background-color: #fafafa;
            img {
                aspect-ratio: 1;
                width: 100%;
                background-color: #dadada;
            }
        }
        .profile {
            color: #fff;
            background-color: #000;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 0.5rem;
            img {
                aspect-ratio: 1;
                width: 48px;
                border-radius: 50%;
            }
        }
        .cart {
            background-color: #dadada;
        }
    </style>
</head>
<body>
    <nav>
        <ul>
            <?php foreach (site()->breadcrumb() as $crumb) { ?>
                <li><a href="<?= $crumb->slug() === 'home' ? url('kart') : $crumb->url() ?>"><?= $crumb->title() ?></a></li>
            <?php } ?>
            <li><a href="<?= url('cart') ?>">Cart (<?= kart()->cart()->quantity() ?>)</a></li>
        </ul>
    </nav>
    <hr>
    <!-- SLOT: default -->
    <?= $slots->default() ?>
    <!-- /SLOT: default -->
</body>
</html>
