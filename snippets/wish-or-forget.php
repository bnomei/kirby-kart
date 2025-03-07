<?php
$product ??= $page;
?>
<?php if (! kart()->wishlist()->has($product)) { ?>
<form method="POST" action="<?= $product->wish() ?>">
    <button type="submit" onclick="this.disabled=true;this.form.submit();" class="cursor-pointer px-4 py-2 bg-kart text-white border-1 border-kart rounded-md group" title="add to wishlist">
        <svg class="w-4 h-4 group-hover:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><g fill="currentColor" stroke-miterlimit="10"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M14.328,2.672 c-1.562-1.562-4.095-1.562-5.657,0C8.391,2.952,8.18,3.27,8,3.601c-0.18-0.331-0.391-0.65-0.672-0.93 c-1.562-1.562-4.095-1.562-5.657,0c-1.562,1.562-1.562,4.095,0,5.657L8,14.5l6.328-6.172C15.891,6.766,15.891,4.234,14.328,2.672z"></path> </g></svg>
        <svg class="w-4 h-4 hidden group-hover:block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><g fill="currentColor"><path d="M7.313,14.705c.386,.365,.989,.365,1.374,0l5.995-5.668c1.757-1.757,1.757-4.607,0-6.364-1.757-1.757-4.607-1.757-6.364,0-.121,.121-.214,.259-.318,.389-.104-.13-.197-.268-.318-.389C5.925,.916,3.075,.916,1.318,2.673S-.439,7.28,1.318,9.037l5.995,5.668Z" fill="currentColor"></path></g></svg>
    </button>
</form>
<?php } else { ?>
<form method="POST" action="<?= $product->forget() ?>">
    <button type="submit" onclick="this.disabled=true;this.form.submit();" class="cursor-pointer px-4 py-2 bg-kart text-white border-1 border-kart rounded-md group" title="remove from wishlist">
        <svg class="w-4 h-4 hidden group-hover:block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><g fill="currentColor" stroke-miterlimit="10"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M14.328,2.672 c-1.562-1.562-4.095-1.562-5.657,0C8.391,2.952,8.18,3.27,8,3.601c-0.18-0.331-0.391-0.65-0.672-0.93 c-1.562-1.562-4.095-1.562-5.657,0c-1.562,1.562-1.562,4.095,0,5.657L8,14.5l6.328-6.172C15.891,6.766,15.891,4.234,14.328,2.672z"></path> </g></svg>
        <svg class="w-4 h-4 group-hover:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><g fill="currentColor"><path d="M7.313,14.705c.386,.365,.989,.365,1.374,0l5.995-5.668c1.757-1.757,1.757-4.607,0-6.364-1.757-1.757-4.607-1.757-6.364,0-.121,.121-.214,.259-.318,.389-.104-.13-.197-.268-.318-.389C5.925,.916,3.075,.916,1.318,2.673S-.439,7.28,1.318,9.037l5.995,5.668Z" fill="currentColor"></path></g></svg>
    </button>
</form>
<?php }
