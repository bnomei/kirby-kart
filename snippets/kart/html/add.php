<?php
/** @var ProductPage $page */
$product ??= $page;

if ($product->inStock()) {
    ?><form method="POST" action="<?= $product->add() ?>">
    <input type="hidden" name="redirect" value="<?= $redirect ?? $page->url() ?>">
    <button type="submit" onclick="this.disabled=true;this.form.submit();" class="cursor-pointer px-4 py-2 bg-kart text-white hover:text-kart hover:bg-white border-1 border-kart rounded-md" title="add to cart">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g fill="currentColor" stroke-linecap="square" stroke-linejoin="miter" stroke-miterlimit="10"> <path d="M6 22C6.55228 22 7 21.5523 7 21C7 20.4477 6.55228 20 6 20C5.44772 20 5 20.4477 5 21C5 21.5523 5.44772 22 6 22Z" fill="currentColor" stroke="currentColor" stroke-width="2"></path> <path d="M19 23V15" stroke="currentColor" fill="none" stroke-width="2"></path> <path d="M15 19H23" stroke="currentColor" fill="none" stroke-width="2"></path> <path d="M4.821 6H22L21 11M1 2H4L5.75469 14.2828C5.89545 15.2681 6.73929 16 7.73459 16H11" stroke="currentColor" fill="none" stroke-width="2"></path> </g></svg>
    </button>
</form>
<?php } else { ?>
    <div class="px-4 py-2 bg-kart hover:bg-gcred text-white border-1 border-kart hover:border-gcred rounded-md" title="out of stock">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><title>empty</title><g fill="currentColor"><path d="M4.932,19.068l1.414-1.414A8,8,0,1,1,17.654,6.346l1.414-1.414A10,10,0,1,0,4.932,19.068Z" fill="currentColor"></path><path d="M19.365,8.877A8,8,0,0,1,8.877,19.365l-1.5,1.5a9.987,9.987,0,0,0,13.48-13.48Z" fill="currentColor"></path><path d="M2,23a1,1,0,0,1-.707-1.707l20-20a1,1,0,0,1,1.414,1.414l-20,20A1,1,0,0,1,2,23Z" fill="currentColor"></path></g></svg>
    </div>
<?php }
