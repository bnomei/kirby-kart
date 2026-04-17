<?php

use Bnomei\Kart\Category;
use Bnomei\Kart\Models\ProductPage;
use Bnomei\Kart\Tag;

snippet('kart/kart', slots: true);
// COPY and modify the code below this line --------
?>

<main>
    <search>
        <?php foreach (kart()->categories() as $category) {
            /** @var Category $category */ ?>
            <a class="<?= $category->isActive() ? 'is-active' : '' ?>" href="<?= $category->urlWithParams() ?>"><?= $category ?></a>
        <?php } ?>
        <br>
        <?php foreach (kart()->tags() as $tag) {
            /** @var Tag $tag */ ?>
            <a class="<?= $tag->isActive() ? 'is-active' : '' ?>" href="<?= $tag->urlWithParams() ?>"><?= $tag ?></a>
        <?php } ?>
    </search>

    <output class="cards">
        <?php foreach (kart()->productsByParams() as $product) {
            /** @var ProductPage $product */
            snippet('kart/product-card', ['product' => $product]);
        } ?>
    </output>
</main>
