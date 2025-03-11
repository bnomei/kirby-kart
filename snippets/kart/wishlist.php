<fieldset>
    <legend>Wishlist (<?= kart()->wishlist()->lines()->count() ?>)</legend>
    <menu>
    <?php foreach (kart()->wishlist()->lines() as $line) {
        /** @var \Bnomei\Kart\CartLine $line */
        /** @var ProductPage $product */
        $product = $line->product(); ?>
        <li>
            <a href="<?= $product->url() ?>"><?= $product->title() ?></a>
            <div>
                <form method="POST" onclick="this.disabled=true;this.form.submit();" action="<?= $product->now() ?>">
                    <button type="submit">⊼</button>
                </form>
                <form method="POST" action="<?= $product->forget() ?>">
                    <button type="submit" onclick="this.disabled=true;this.form.submit();">⊗</button>
                </form>
            </div>
        </li>
    <?php } ?>
    </menu>
</fieldset>
