<?php
snippet('kart/kart', slots: true);
// COPY and modify the code below this line --------
?>

<main>
    <?php if (kirby()->user()?->isCustomer()) {
        snippet('kart/profile');
    } else {
        snippet('kart/signup-magic');
    } ?>
</main>
