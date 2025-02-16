<?php if ($user = kirby()->user()) { ?>
    <div class="flex space-x-2 items-c">
        <?php snippet('gravatar', [
            'email' => $user->email(),
            'name' => $user->nameOrEmail(),
            'size' => 48 * 2, // w/h-12 retina
        ]) ?>
        <div><?= $user->email() ?></div>
        <?php snippet('kart/logout') ?>
    </div>
<?php } else {
    snippet('kart/login');
} ?>
