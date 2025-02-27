<?php $products = page('products')->children(); ?>
<ul class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-8 py-8">
    <?php /** @var ProductPage $product */
    foreach ($products as $product) { ?>
        <li class="block border border-kart bg-kart text-white rounded-md overflow-hidden">
            <a href="<?= $product->url() ?>"><img class="aspect-square object-cover w-full h-auto" src="<?= $product->gallery()->toFile()?->url() ?>" alt="<?= $product->title() ?>">
            </a>
            <div class="px-2 py-1 flex justify-between">
                <div>
                    <h2 class="text-xl mt-1"><?= $product->title() ?></h2>
                    <span><?= $product->formattedPrice() ?></span>
                </div>

                <div>
                    <?php snippet(option('tests.frontend').'/buy', [
                        'product' => $product,
                        'redirect' => site()->url().'/cart', // ready for checkout
                    ]) ?>
                    <?php snippet(option('tests.frontend').'/wish-or-forget', [
                        'product' => $product,
                    ]) ?>
                </div>
            </div>
        </li>
    <?php } ?>
</ul>