<?php snippet('layout', slots: true) ?>

<?php $products = page('products'); ?>
<h2><?= $products->title() ?></h2>
<ul>
    <?php foreach ($products as $product) { ?>
        <li><a href="<?= $product->url() ?>"><?= $product->title() ?></a></li>
    <?php } ?>
</ul>
