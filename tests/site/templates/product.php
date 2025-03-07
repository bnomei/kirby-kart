<?php snippet('layout', slots: true) ?>

<?php
/** @var ProductPage $page */
$product ??= $page;
?>

<section>
    <article >
        <img src="<?= $product->gallery()->toFile()?->url() ?>" alt="<?= $product->title() ?>">
        <h1><?= $product->title() ?></h1>
        <?= $product->description()->kirbytext() ?>
        <div><?= $product->formattedPrice() ?></div>
        <?php snippet('kart/add') ?>
        <?php snippet('kart/wish-or-forget') ?>
    </article>
    <aside>
        <?php snippet('kart/profile') ?>
        <?php snippet('kart/cart') ?>
        <?php snippet('kart/wishlist') ?>
    </aside>
</section>
<?php snippet('kart/json-ld/product') ?>
