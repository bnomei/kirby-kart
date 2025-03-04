<?php snippet('layout', slots: true) ?>

<?php
$products = kart()->productsByParams();
?>

<nav class="bg-kart p-4 space-x-2 mb-12 rounded-md flex justify-center">
    <?php foreach (kart()->categories()->sortBy('count', 'desc') as $c) {
        /** @var \Bnomei\Kart\Category $c */
        ?>
        <a class="bg-kart text-gcyellow hover:bg-gcyellow hover:text-kart border-2 border-gcyellow font-bold [.is-active]:bg-gcyellow [.is-active]:text-kart <?= $c->isActive() ? 'is-active' : '' ?> px-3 rounded-full" href="<?= $c->urlWithParams() ?>"><?= $c ?></a>
    <?php } ?>
    <div class="w-12"></div>
    <?php foreach (kart()->tags()->sortBy('count', 'desc') as $t) {
        /** @var \Bnomei\Kart\Tag $t */
        ?>
        <a class="bg-kart text-gcyellow hover:bg-gcyellow hover:text-kart border-2 border-gcyellow font-bold [.is-active]:bg-gcyellow [.is-active]:text-kart <?= $t->isActive() ? 'is-active' : '' ?> px-3 rounded-full" href="<?= $t->urlWithParams() ?>"><?= $t ?></a>
    <?php } ?>
</nav>

<ul class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-8">
    <?php /** @var ProductPage $product */
    foreach ($products as $product) { ?>
        <li class="col-span-1">
            <?php snippet('product-card', ['product' => $product]); ?>
        </li>
    <?php } ?>
</ul>
