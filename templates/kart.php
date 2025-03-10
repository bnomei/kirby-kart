<?php
snippet('kart/kart', slots: true);
// COPY and modify the code below this line --------
?>

<main>
    <div class="cards">
        <?php foreach (kart()->products()->random(min(2, kart()->products()->count())) as $product) {
            snippet('kart/product-card', ['product' => $product]);
        } ?>
    </div>
    <footer>
        <?php if (kirby()->user()?->isCustomer()) {
            snippet('kart/profile');
        } else {
            snippet('kart/login-magic');
            ?><br>or <a href="<?= url(\Bnomei\Kart\Router::SIGNUP_MAGIC) ?>">sign up</a><?php
        } ?>

    </footer>
</main>
