<div>
    <h3 class="font-bold">Wishlist <small>(<?= kart()->wishlist()->lines()->count() ?>)</small></h3>

    <ol>
    <?php foreach (kart()->wishlist()->lines() as $line) {
        $product = $line->product(); ?>
        <li class="flex space-x-2 items-center">
            <a class="hover:underline" href="<?= $product->url() ?>"><?= $product->title() ?></a>
            <span class="grow"><!-- spacer --></span>
            <form method="POST" action="<?= $product->forget() ?>">
                <button type="submit" class="cursor-pointer px-4 py-2 text-kart" title="remove from wishlist">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g fill="currentColor" stroke-linecap="square" stroke-linejoin="miter" stroke-miterlimit="10"><line x1="14.5" y1="12.5" x2="9.5" y2="17.5" fill="none" stroke="currentColor"></line><line x1="14.5" y1="17.5" x2="9.5" y2="12.5" fill="none" stroke="currentColor"></line><path d="m18.73,10h.02s-.42,10.083-.42,10.083c-.045,1.071-.926,1.917-1.998,1.917H7.668c-1.072,0-1.954-.845-1.998-1.917l-.42-10.083h.02" fill="none" stroke="currentColor"></path><line x1="3" y1="6" x2="21" y2="6" fill="none" stroke="currentColor"></line><path d="m9,6V2h6v4" fill="none" stroke="currentColor" stroke-linecap="butt"></path></g></svg>
                </button>
            </form>
        </li>
    <?php } ?>
    </ol>
</div>
