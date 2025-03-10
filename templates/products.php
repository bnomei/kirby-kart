<?php
snippet('kart/kart', slots: true);
// COPY and modify the code below this line --------
?>

<main>
    <nav>
        <?php foreach (kart()->categories()->sortBy('count', 'desc') as $category) {
            /** @var \Bnomei\Kart\Category $category */ ?>
            <a class="<?= $category->isActive() ? 'is-active' : '' ?>" href="<?= $category->urlWithParams() ?>"><?= $category ?></a>
        <?php } ?>
        <br>
        <?php foreach (kart()->tags()->sortBy('count', 'desc') as $tag) {
            /** @var \Bnomei\Kart\Tag $tag */ ?>
            <a class="<?= $tag->isActive() ? 'is-active' : '' ?>" href="<?= $tag->urlWithParams() ?>"><?= $tag ?></a>
        <?php } ?>
    </nav>

    <article class="cards">
        <?php foreach (kart()->productsByParams() as $product) {
            /** @var ProductPage $product */
            snippet('kart/product-card', ['product' => $product]);
        } ?>
    </article>
</main>
