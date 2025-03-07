<?php
/** @var ProductPage $page */
$product ??= $page;

if ($product->inStock()) { ?>
    <form method="POST" action="<?= $product->add() ?>">
        <input type="hidden" name="redirect" value="<?= $redirect ?? $page->url() ?>">
        <button type="submit" onclick="this.disabled=true;this.form.submit();">Add</button>
    </form>
<?php } else { ?>
    <div>Out of stock</div>
<?php }
