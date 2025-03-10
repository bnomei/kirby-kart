<div class="cart">
    <h3>Cart (<?= kart()->cart()->quantity() ?>)</h3
    <ol>
        <?php foreach (kart()->cart()->lines() as $line) {
            /** @var \Bnomei\Kart\CartLine $line */
            /** @var ProductPage $product */
            $product = $line->product(); ?>
            <li>
                <a href="<?= $product->url() ?>"><?= $product->title() ?></a>
                <?php if ($line->hasStockForQuantity() === false) { ?>
                    <span><?= $product->stock() ?> of <?= $line->quantity() ?>x</span>
                <?php } else { ?>
                    <span><?= $line->quantity() ?>x</span>
                <?php } ?>
                <form method="POST" action="<?= $product->add() ?>">
                    <button type="submit" onclick="this.disabled=true;this.form.submit();">+</button>
                </form>
                <form method="POST" action="<?= $product->remove() ?>">
                    <button onclick="this.disabled=true;this.form.submit();" type="submit">-</button>
                </form>
                <form method="POST" action="<?= $product->later() ?>">
                    <button onclick="this.disabled=true;this.form.submit();" type="submit">Move to wishlist</button>
                </form>
            </li>
        <?php } ?>
    </ol>
    <div><?= kart()->cart()->formattedSubtotal() ?> +tax</div>
    <form method="POST" action="<?= kart()->urls()->cart_checkout() ?>">
        <?php // TODO: You should add an invisible CAPTCHA here, like...?>
        <?php // snippet('kart/turnstile-form')?>
        <input type="hidden" name="redirect" value="<?= $page->url() ?>">
        <button type="submit" onclick="this.disabled=true;this.form.submit();" <?php e(kart()->cart()->canCheckout() === false, 'disabled') ?>>Checkout</button>
    </form>
</div>
