<?php
/** @var ProductPage $page */
$product ??= $page;
?><form method="POST" action="<?= $product->add() ?>">
    <input type="hidden" name="redirect" value="<?= $redirect ?? $page->url() ?>">
    <button type="submit" onclick="this.disabled=true;this.form.submit();" class="cursor-pointer px-4 py-2 bg-kart text-white rounded-md" title="add to cart">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g fill="currentColor" stroke-linecap="square" stroke-linejoin="miter" stroke-miterlimit="10"> <path d="M6 22C6.55228 22 7 21.5523 7 21C7 20.4477 6.55228 20 6 20C5.44772 20 5 20.4477 5 21C5 21.5523 5.44772 22 6 22Z" fill="currentColor" stroke="currentColor" stroke-width="2"></path> <path d="M19 23V15" stroke="currentColor" fill="none" stroke-width="2"></path> <path d="M15 19H23" stroke="currentColor" fill="none" stroke-width="2"></path> <path d="M4.821 6H22L21 11M1 2H4L5.75469 14.2828C5.89545 15.2681 6.73929 16 7.73459 16H11" stroke="currentColor" fill="none" stroke-width="2"></path> </g></svg>
    </button>
</form>
