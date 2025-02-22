<?php snippet('layout', slots: true) ?>

<div class="mx-auto max-w-md">
    <aside class="border border-gray-100 mt-12 md:mt-0 md:ml-4 p-4 space-y-8 bg-gray-50">
        <?php snippet('profile', ['logout' => false]) ?>
        <?php snippet(option('tests.frontend').'/cart') ?>
    </aside>
</div>
