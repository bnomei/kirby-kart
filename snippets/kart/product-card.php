<?php
/** @var ProductPage $page */
$product ??= $page;
?>
<div class="card">
    <a href="<?= $product->url() ?>">
        <img src="<?= $product->gallery()->toFile()?->url() ?>" alt="">
        <h2><?= $product->title() ?></h2>
    </a>
    <div><?= $product->formattedPrice() ?></div>
    <?php snippet('kart/buy', [
        'product' => $product,
        'redirect' => kart()->urls()->cart(), // go to cart and be ready for checkout
    ]) ?>
    <?php snippet('kart/wish-or-forget', [
        'product' => $product,
    ]) ?>
</div>