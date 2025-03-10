<div class="profile">
<?php if ($user = kirby()->user()) { ?>
    <img src="<?= $user->gravatar(48 * 2) ?>" alt="">
    <div><?= $user->nameOrEmail() ?></div>
    <?php snippet('kart/logout') ?>
<?php } else {
        snippet('kart/login');
    } ?>
</div>
