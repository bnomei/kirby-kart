<div>
    <h3 class="font-bold">Cart <small>(<?= kart()->cart()->quantity() ?>)</small></h3>

    <ol>
        <?php
        /** @var \Bnomei\Kart\CartLine $line */
        foreach (kart()->cart()->lines() as $line) {
            /** @var ProductPage $product */
            $product = $line->product(); ?>
            <li class="flex space-x-1 py-1 items-center">
                <a class="hover:underline" href="<?= $product->url() ?>"><?= $product->title() ?></a>
                <span class="grow"><!-- spacer --></span>
                <?php if (! $line->inStockForQuantity()) { ?>
                    <span class="ml-1 text-sm px-2 rounded-sm bg-gcred text-white"><?= $product->stock() ?></span><span class="text-gcgray">of <?= $line->quantity() ?>x</span>
                <?php } else { ?>
                    <span class="text-gcgray"><?= $line->quantity() ?>x</span>
                <?php } ?>
                <span class="w-4"><!-- spacer --></span>
                <form method="POST" action="<?= $product->add() ?>">
                    <button type="submit" onclick="this.disabled=true;this.form.submit();" class="flex pb-px justify-center items-center cursor-pointer w-5 h-5 bg-kart text-white rounded-xs">+</button>
                </form>
                <form method="POST" onclick="this.disabled=true;this.form.submit();" action="<?= $product->remove() ?>">
                    <button type="submit" class="flex pb-px justify-center items-center cursor-pointer w-5 h-5 bg-kart text-white rounded-xs">-</button>
                </form>
                <form method="POST" onclick="this.disabled=true;this.form.submit();" action="<?= $product->move() ?>">
                    <button type="submit" class="flex pb-px justify-center items-center cursor-pointer w-5 h-5 bg-kart text-white rounded-xs">❤︎</button>
                </form>
            </li>
        <?php } ?>
    </ol>

    <div class="border-t border-black flex flex-col items-end py-2 mt-2">
        <div><span class="font-bold"><?= kart()->cart()->formattedSubtotal() ?></span>&nbsp;<span class="text-sm">+tax</span></div>
        <div class="h-4"><!-- spacer --></div>
        <form method="POST" action="<?= kart()->checkout() ?>">
            <button type="submit" class="cursor-pointer px-6 py-2 bg-gcgreen text-white rounded-md border-gray-50 border-1 outline-2 outline-gray-50 hover:outline-gcgreen text-xl font-semibold flex items-center space-x-3 disabled:bg-gcgray disabled:cursor-not-allowed disabled:outline-none group" <?php if (! kart()->cart()->allInStock()) { ?>disabled<?php } ?>>
                <span><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g fill="currentColor"> <path d="M15 17C13.8954 17 13 17.8954 13 19V22C13 23.1046 13.8954 24 15 24H22C23.1046 24 24 23.1046 24 22V19C24 17.8954 23.1046 17 22 17H15Z" fill="currentColor"></path> <path fill-rule="evenodd" clip-rule="evenodd" d="M18.5 15C17.6713 15 17 15.6713 17 16.5V19.5H15V16.5C15 14.5667 16.5667 13 18.5 13C20.4333 13 22 14.5667 22 16.5V19.5H20V16.5C20 15.6713 19.3287 15 18.5 15Z" fill="currentColor"></path> <path fill-rule="evenodd" clip-rule="evenodd" d="M1 10V18C1 19.6569 2.34315 21 4 21H11V19C11 17.481 11.8467 16.1597 13.0939 15.4825C13.5711 12.9309 15.8098 11 18.5 11C20.3603 11 22.0047 11.9233 23 13.3367V10H1ZM4 17V15H8V17H4Z" fill="currentColor"></path> <path d="M1 6C1 4.34315 2.34315 3 4 3H20C21.6569 3 23 4.34315 23 6V7H1V6Z" fill="currentColor"></path> </g></svg></span><span>Checkout</span></button>
        </form>
    </div>
</div>
