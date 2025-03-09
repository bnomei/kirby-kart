<?php
snippet('kart', slots: true) ?>

<main>
    <nav>
        <?php foreach (kart()->products()->random(2) as $product) {
            snippet('product-card', ['product' => $product]);
        } ?>
    </nav>
</main>
