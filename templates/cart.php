<?php
snippet('kart/kart', slots: true);
// COPY and modify the code below this line --------
?>

<main>
    <header>
        <?php snippet('kart/profile') ?>
    </header>

    <?php snippet('kart/cart') ?>
    <?php snippet('kart/checkout-json-ld') ?>
</main>
