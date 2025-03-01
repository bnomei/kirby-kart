<?php snippet('layout', slots: true) ?>

<div class="mx-auto max-w-md min-h-[62vh] flex items-center justify-center">
    <div class="p-4 md:p-12 space-y-8 bg-gray-50 min-w-sm rounded-md">
        <?php snippet('profile', ['logout' => false]) ?>
        <?php snippet(option('tests.frontend').'/cart') ?>
    </div>
</div>
