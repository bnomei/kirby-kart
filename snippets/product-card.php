<?php
/** @var ProductPage $page */
$product ??= $page;
?>
<div>
    <a href="<?= $product->url() ?>">
        <img src="<?= $product->gallery()->toFile()?->url() ?>" alt="<?= $product->title() ?>">
        <h2><?= $product->title() ?></h2>
    </a>
    <div><?= $product->formattedPrice() ?></div>
    <?php snippet('kart/buy', [
        'product' => $product,
        'redirect' => site()->url().'/cart', // ready for checkout
    ]) ?>
    <?php snippet('kart/wish-or-forget', [
        'product' => $product,
    ]) ?>
</div>