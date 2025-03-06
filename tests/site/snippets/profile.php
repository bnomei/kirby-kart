<?php
$logout ??= true;
if ($user = kirby()->user()) { ?>
    <div class="flex space-x-2 items-center">
        <img class="rounded-full w-12 h-12" src="<?= $user->gravatar(48 * 2) ?>" alt="<?= $user->nameOrEmail() ?>">
        <div><?= $user->email() ?></div>
        <?php if ($logout) { ?>
            <div class="grow"><!-- spacer --></div>
            <?php snippet('kart/logout') ?>
        <?php } ?>
    </div>
<?php } else {
    snippet('kart/login');
} ?>
