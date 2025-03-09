<?php
snippet('kart', slots: true);
// COPY and modify the code below this line --------

/** @var ProductPage $page */
$product ??= $page;
?>

<main>
    <article>
        <img src="<?= $product->gallery()->toFile()?->url() ?>" alt="<?= $product->title() ?>">
        <h1><?= $product->title() ?></h1>
        <?= $product->description()->kirbytext() ?>
        <div><?= $product->formattedPrice() ?></div>
        <?php snippet('kart/add') ?>
        <?php snippet('kart/wish-or-forget') ?>
    </article>
</main>

<aside>
    <header>
        <?php snippet('kart/profile') ?>
    </header>

    <?php snippet('kart/cart') ?>
    <hr>
    <?php snippet('kart/wishlist') ?>
</aside>

<?php snippet('kart/product/json-ld') ?>
