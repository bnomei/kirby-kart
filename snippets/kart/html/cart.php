<div>
    <h3 class="font-bold">Cart <small>(<?= kart()->cart()->lines()->count() ?>)</small></h3>

    <ol>
        <?php
        /** @var \Bnomei\Kart\CartLine $line */
        foreach (kart()->lines() as $line) {
            $product = $line->product(); ?>
            <li class="flex space-x-1 py-1 items-center">
                <a class="hover:underline" href="<?= $product->url() ?>"><?= $product->title() ?></a>
                <span class="grow"><!-- spacer --></span>
                <span class="text-gray-500"><?= $line->quantity() ?>x</span>
                <span class="w-4"><!-- spacer --></span>
                <form method="POST" action="<?= $product->add() ?>">
                    <button type="submit" class="flex pb-px justify-center items-center cursor-pointer w-5 h-5 bg-kart text-white rounded-xs">+</button>
                </form>
                <form method="POST" action="<?= $product->remove() ?>">
                    <button type="submit" class="flex pb-px justify-center items-center cursor-pointer w-5 h-5 bg-kart text-white rounded-xs">-</button>
                </form>
            </li>
        <?php } ?>
    </ol>

    <div class="border-t border-black flex flex-col items-end py-2">
        <div class="text-sm">Subtotal: <?= kart()->sum() ?></div>
        <div class="text-sm">Tax: <?= kart()->tax() ?></div>
        <div class="font-bold border-t mt-2 pt-1">Total: <?= kart()->sumtax() ?></div>
        <div class="h-4"><!-- spacer --></div>
        <form method="POST" action="<?= kart()->checkout() ?>">
            <input type="hidden" name="redirect" value="<?= $page->url() ?>">
            <button type="submit" class="cursor-pointer px-3 py-1 bg-kart text-white rounded-md">Checkout</button>
        </form>
    </div>
</div>
