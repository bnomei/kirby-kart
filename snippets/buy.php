<?php
/** @var ProductPage $page */
$product ??= $page;

if ($product->inStock()) {
    ?><form method="POST" action="<?= $product->buy() ?>">
    <input type="hidden" name="redirect" value="<?= $redirect ?? $page->url() ?>">
    <button type="submit" onclick="this.disabled=true;this.form.submit();" class="cursor-pointer px-4 py-2 bg-kart text-white border-kart hover:bg-gcgreen hover:border-gcgreen rounded-md" title="buy now">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g fill="currentColor"> <path fill-rule="evenodd" clip-rule="evenodd" d="M1 11H7V13H1V11Z" fill="currentColor"></path> <path d="M6 23C7.10457 23 8 22.1046 8 21C8 19.8954 7.10457 19 6 19C4.89543 19 4 19.8954 4 21C4 22.1046 4.89543 23 6 23Z" fill="currentColor"></path> <path d="M20 23C21.1046 23 22 22.1046 22 21C22 19.8954 21.1046 19 20 19C18.8954 19 18 19.8954 18 21C18 22.1046 18.8954 23 20 23Z" fill="currentColor"></path> <path fill-rule="evenodd" clip-rule="evenodd" d="M7.734 17H18.36C19.785 17 21.023 15.985 21.301 14.589L23.219 5H5.438L4.867 1H0V3H3.133L3.98954 9H10V11H4.27506L4.56057 13H7V15H4.90599C5.32385 16.1727 6.45195 17 7.734 17ZM12 9V11H15V9H12Z" fill="currentColor"></path> </g></svg>
    </button>
</form>
<?php } else { ?>
    <div class="px-4 py-2 hover:bg-gcred text-white rounded-md" title="out of stock">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><title>empty</title><g fill="currentColor"><path d="M4.932,19.068l1.414-1.414A8,8,0,1,1,17.654,6.346l1.414-1.414A10,10,0,1,0,4.932,19.068Z" fill="currentColor"></path><path d="M19.365,8.877A8,8,0,0,1,8.877,19.365l-1.5,1.5a9.987,9.987,0,0,0,13.48-13.48Z" fill="currentColor"></path><path d="M2,23a1,1,0,0,1-.707-1.707l20-20a1,1,0,0,1,1.414,1.414l-20,20A1,1,0,0,1,2,23Z" fill="currentColor"></path></g></svg>
    </div>
<?php }
