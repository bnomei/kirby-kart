<?php snippet('layout', slots: true) ?>

<?php $products = kart()->products()->random(2); ?>
<ul class="grid grid-cols-2 gap-x-4 gap-y-8">
    <?php /** @var ProductPage $product */
    foreach ($products as $product) { ?>
        <li class="col-span-1">
            <?php snippet('product-card', ['product' => $product]); ?>
        </li>
    <?php } ?>
</ul>