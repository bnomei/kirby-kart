<?php snippet('layout', slots: true) ?>

<section>
    <article>
        <?php foreach (kart()->products()->random(2) as $product) {
            snippet('product-card', ['product' => $product]);
        } ?>
    </article>
</section>
