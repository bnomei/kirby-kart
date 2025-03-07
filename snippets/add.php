<?php
/** @var ProductPage $page */
$product ??= $page;

if ($product->inStock()) { ?>
    <form method="POST" action="<?= $product->add() ?>">
        <input type="hidden" name="redirect" value="<?= $redirect ?? $page->url() ?>">
        <button type="submit" onclick="this.disabled=true;this.form.submit();">add</button>
    </form>
<?php } else { ?>
    <div>out of stock</div>
<?php }
