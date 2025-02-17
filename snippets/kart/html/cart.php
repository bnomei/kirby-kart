<div>
    <h3 class="font-bold">Cart <small>(<?= kart()->products()->count() ?>)</small></h3>

    <ol>
        <?php foreach (kart()->products() as $product) { ?>
            <li class="flex space-x-2">
                <a class="hover:underline" href="<?= $product->url() ?>"><?= $product->title() ?></a>
                <span class="grow"><!-- spacer --></span>
                <span><?= $product->quantity() ?>x</span>
                <span class="grow"><!-- spacer --></span>
                <form method="POST" action="<?= $product->add() ?>">
                    <button type="submit">+</button>
                </form>
                <form method="POST" action="<?= $product->remove() ?>">
                    <button type="submit">-</button>
                </form>
            </li>
        <?php } ?>
    </ol>

    <div class="border-t border-black flex flex-col items-end py-2">
        <div class="text-sm">Subtotal: <?= kart()->sum() ?></div>
        <div class="text-sm">Tax: <?= kart()->tax() ?></div>
        <div class="font-bold border-t mt-2 pt-1">Total: <?= kart()->sumtax() ?></div>
        <div class="h-4"><!-- spacer --></div>
        <form method="GET" action="<?= kart()->checkout() ?>">
            <button type="submit" class="cursor-pointer px-3 py-1 bg-kart text-white rounded-md">Checkout</button>
        </form>
    </div>
</div>
