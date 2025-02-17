<div>
    <h3>Cart (<?= kart()->products()->count() ?>)</h3>

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

    <div class="border-t border-black flex justify-end py-2">
        <div class="text-sm">Subtotal: <?= kart()->sum() ?></div>
        <div class="text-sm">Tax: <?= kart()->tax() ?></div>
        <div class="font-bold">Total: <?= kart()->sumtax() ?></div>
        <p> (TODO: should be formated and with currency)</p>

        <form method="GET" action="<?= kart()->checkout() ?>">
            <button type="submit">Checkout</button>
        </form>
    </div>
</div>
