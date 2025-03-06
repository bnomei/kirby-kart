<?php
$logout ??= true;
if ($user = kirby()->user()) { ?>
    <div class="flex space-x-2 items-center">
        <?php snippet('gravatar', ['user' => $user]) ?>
        <div><?= $user->email() ?></div>
        <?php if ($logout) { ?>
            <div class="grow"><!-- spacer --></div>
            <?php snippet('kart/logout') ?>
        <?php } ?>
    </div>
<?php } else {
    snippet('kart/login');
} ?>
