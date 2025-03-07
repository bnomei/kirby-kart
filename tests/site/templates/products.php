<?php snippet('layout', slots: true) ?>

<section>
    <header>
        <?php foreach (kart()->categories()->sortBy('count', 'desc') as $category) {
            /** @var \Bnomei\Kart\Category $category */ ?>
            <a class="<?php e($category->isActive(), 'is-active') ?>" href="<?= $category->urlWithParams() ?>"><?= $category ?></a>
        <?php } ?>
        <?php foreach (kart()->tags()->sortBy('count', 'desc') as $tag) {
            /** @var \Bnomei\Kart\Tag $tag */ ?>
            <a class="<?php e($tag->isActive(), 'is-active') ?>" href="<?= $tag->urlWithParams() ?>"><?= $tag ?></a>
        <?php } ?>
    </header>
    <article>
        <?php foreach (kart()->productsByParams() as $product) {
            /** @var ProductPage $product */
            snippet('product-card', ['product' => $product]);
        } ?>
    </article>
</section>
