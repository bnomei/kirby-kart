<div>
    <h3>Wishlist (<?= kart()->wishlist()->lines()->count() ?>)</h3>
    <ol>
    <?php foreach (kart()->wishlist()->lines() as $line) {
        /** @var \Bnomei\Kart\CartLine $line */
        /** @var ProductPage $product */
        $product = $line->product(); ?>
        <li>
            <a href="<?= $product->url() ?>"><?= $product->title() ?></a>
            <form method="POST" action="<?= $product->forget() ?>">
                <button type="submit" onclick="this.disabled=true;this.form.submit();">Remove from wishlist</button>
            </form>
        </li>
    <?php } ?>
    </ol>
</div>
