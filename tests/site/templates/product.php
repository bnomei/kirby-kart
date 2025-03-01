<?php snippet('layout', slots: true) ?>
<?php
/** @var ProductPage $page */
$product ??= $page;
?>

<section class="flex flex-col md:flex-row">
    <article class="md:w-2/3 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <figure class="col-span-2 md:col-span-1 lg:col-span-2">
            <?php if ($image = $product->gallery()->toFile()) { ?>
                <img src="<?= $image->url() ?>" alt="<?= $image->alt() ?>" class="w-full">
            <?php } ?>
        </figure>
        <div class="col-span-2 md:col-span-2 lg:col-span-3 space-y-2">
            <h1 class="text-4xl pt-8 pb-4"><?= $product->title() ?></h1>
            <p class="text-gcgray pb-4"><?= $product->description()->excerpt(140) ?></p>
            <p>
                <span class="text-xl"><?= $product->formattedPrice() ?></span>
            </p>
            <div class="mt-4 flex space-x-2 mt-2">
                <?php snippet(option('tests.frontend').'/add') ?>
                <?php snippet(option('tests.frontend').'/wish-or-forget') ?>
            </div>
        </div>
    </article>
    <aside class="md:w-1/3 mt-12 md:mt-0 md:ml-8 space-y-12 bg-gray-50 rounded-b-md">
        <div class="bg-gcblack text-white p-4 rounded-t-md">
            <?php snippet('profile') ?>
        </div>
        <div class="p-4">
            <?php snippet(option('tests.frontend').'/cart') ?>
        </div>
        <div class="p-4">
            <?php snippet(option('tests.frontend').'/wishlist') ?>
        </div>
    </aside>
</section>
<?php snippet('kart/json-ld/product') ?>
