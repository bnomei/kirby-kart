<?php snippet('layout', slots: true) ?>

<section class="flex">
    <article class="w-2/3 flex space-x-4">
        <figure class="pr-2">
            <?php if ($image = $page->gallery()->toFile()) { ?>
                <img src="<?= $image->url() ?>" alt="<?= $image->alt() ?>" class="w-[300px]">
            <?php } ?>
        </figure>
        <div class="space-y-2">
            <h1 class="text-2xl"><?= $page->title() ?></h1>
            <p class="text-gray-500"><?= $page->description()->excerpt(140) ?></p>
            <p>
                <span class="text-xl"><?= $page->formattedSumTax() ?></span><br>
                (incl. <?= $page->tax() ?>% tax)
            </p>
            <div class="mt-4 flex space-x-2">
                <?php snippet(option('tests.frontend').'/add') ?>
                <?php snippet(option('tests.frontend').'/wish-or-forget') ?>
            </div>
        </div>
    </article>
    <aside class="w-1/3 border-l border-gray-100 ml-4 p-4 space-y-8">
        <?php snippet('profile') ?>
        <?php snippet(option('tests.frontend').'/cart') ?>
        <?php snippet(option('tests.frontend').'/wishlist') ?>
    </aside>
</section>
<?php snippet('kart/json-ld/product') ?>
