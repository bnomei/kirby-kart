<?php snippet('layout', slots: true) ?>
<?php /** @var OrderPage $page */ ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    <header class="col-span-1 md:col-span-2">
        <h1 class="text-4xl py-8">Your Order <?= $page->title() ?></h1>
    </header>

    <article class="col-span-1 pb-12 border border-gray-200 bg-gray-50 p-4">
        <table>
            <tr>
                <td class="text-gray-500 text-xs text-right pr-4">Invoice Number</td>
                <td>#<?= $page->invoiceNumber() ?></td>
            </tr>
            <tr>
                <td class="text-gray-500 text-xs text-right pr-4">Order Date</td>
                <td><?= $page->paidDate()->toDate('Y-m-d H:i') ?></td>
            </tr>
            <tr>
                <td class="text-gray-500 text-xs text-right pr-4">Order Status</td>
                <td><?= $page->paymentComplete()->toBool() ? 'paid' : 'open' ?></td>
            </tr>
            <tr>
                <td class="text-gray-500 text-xs text-right pr-4">Order Total</td>
                <td><?= $page->formattedTotal() ?></td>
            </tr>
        </table>
    </article>

    <ul class="col-span-1 space-y-12">
        <?php /** @var ProductPage $product */
        foreach ($page->items()->toStructure() as $item) {
            $product = $item->key()->toPage();
            ?>
            <li class="grid grid-cols-2 gap-4 border-t last:border-b border-gray-200 py-4">
                <a class="block col-span-1" href="<?= $product->url() ?>">
                    <img src="<?= $product->gallery()->toFile()?->url() ?>" alt="<?= $product->title() ?>">
                </a>
                <div class="col-span-1">
                    <div class="px-2 py-1 flex justify-between">
                        <span><?= $product->title() ?></span>
                        <span class="grow"><!-- spacer --></span>
                        <span class="text-gray-500"><?= $item->quantity() ?>x</span>
                        <span class="w-4"><!-- spacer --></span>
                        <span><?= $item->price()->toFormattedCurrency() ?></span>
                    </div>
                </div>
            </li>
        <?php } ?>
    </ul>
</div>

