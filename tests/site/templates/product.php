<?php snippet('layout', slots: true) ?>
<?php /** @var ProductPage $page */ ?>

<section class="flex flex-col md:flex-row">
    <article class="md:w-2/3 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <figure class="col-span-2 md:col-span-1 lg:col-span-2">
            <?php if ($image = $page->gallery()->toFile()) { ?>
                <img src="<?= $image->url() ?>" alt="<?= $image->alt() ?>" class="w-full">
            <?php } ?>
        </figure>
        <div class="col-span-2 md:col-span-2 lg:col-span-3 space-y-2">
            <h1 class="text-4xl pt-8 pb-4"><?= $page->title() ?></h1>
            <p class="text-gray-500 pb-4"><?= $page->description()->excerpt(140) ?></p>
            <p>
                <span class="text-xl"><?= $page->formattedPrice() ?></span>
            </p>
            <div class="mt-4 flex space-x-2 mt-2">
                <?php snippet(option('tests.frontend').'/add') ?>
                <?php snippet(option('tests.frontend').'/wish-or-forget') ?>
            </div>
        </div>
    </article>
    <aside class="md:w-1/3 border border-gray-100 mt-12 md:mt-0 md:ml-4 p-4 space-y-8 bg-gray-50">
        <?php snippet('profile') ?>
        <?php snippet(option('tests.frontend').'/cart') ?>
        <?php snippet(option('tests.frontend').'/wishlist') ?>
    </aside>
</section>
<?php snippet('kart/json-ld/product') ?>
