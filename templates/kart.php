<?php
snippet('kart/kart', slots: true) ?>

<main>
    <div class="cards">
        <?php foreach (kart()->products()->random(2) as $product) {
            snippet('kart/product-card', ['product' => $product]);
        } ?>
    </div>
</main>
