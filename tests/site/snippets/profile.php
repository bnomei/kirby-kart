<?php
$logout ??= true;
if ($user = kirby()->user()) { ?>
    <div class="flex space-x-2 items-center">
        <?php snippet('gravatar', [
            'email' => $user->email(),
            'name' => $user->nameOrEmail(),
            'size' => 48 * 2, // w/h-12 retina
        ]) ?>
        <div><?= $user->email() ?></div>
        <?php if ($logout) { ?>
            <div class="grow"><!-- spacer --></div>
            <?php snippet('kart/logout') ?>
        <?php } ?>
    </div>
<?php } else {
    snippet('kart/login');
} ?>
