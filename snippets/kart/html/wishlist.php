<div>
    <h3>Wishlist</h3>

    <ol>
    <?php foreach (kart()->wishlist()->products() as $product) { ?>
        <li class="flex space-x-2">
            <a class="hover:underline" href="<?= $product->url() ?>"><?= $product->title() ?></a>
            <span class="grow"><!-- spacer --></span>
            <form method="POST" action="<?= $product->forget() ?>">
                <button type="submit">remove</button>
            </form>
        </li>
    <?php } ?>
    </ol>
</div>
