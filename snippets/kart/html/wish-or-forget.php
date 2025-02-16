<?php if (! kart()->cart()->has($product)) { ?>
<form method="POST" action="<?= $product->wish() ?>">
    <button type="submit">add to wishlist</button>
</form>
<?php } else { ?>
<form method="POST" action="<?= $product->forget() ?>">
    <button type="submit">remove from wishlist</button>
</form>
<?php }
