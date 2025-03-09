<?php /** @var OrderPage $order */ ?>
<h1>Your Order: <code><?= $order->title() ?></code></h1>

<table>
    <tr>
        <td>Customer</td>
        <td><?= $order->customer()->toUser()?->email() ?></td>
    </tr>
    <tr>
        <td>Invoice Number</td>
        <td>#<?= $order->invoiceNumber() ?></td>
    </tr>
    <tr>
        <td>Order Date</td>
        <td><?= $order->paidDate()->toDate('Y-m-d H:i') ?></td>
    </tr>
    <tr>
        <td>Order Status</td>
        <td><?= $order->paymentComplete()->toBool() ? 'paid' : 'open' ?></td>
    </tr>
    <tr>
        <td>Order Total</td>
        <td><?= $order->formattedTotal() ?></td>
    </tr>
</table>

<table>
    <?php /** @var ProductPage $product */
    foreach ($order->items()->toStructure() as $item) {
        $product = $item->key()->toPage();
        ?>
        <tr>
            <td><img class="max-w-[128px]" src="<?= $product->gallery()->toFile()?->resize(128)->url() ?>" alt="<?= $product->title() ?>"></td>
            <td><?= $product->title() ?></td>
            <td><?= $item->quantity() ?>x</td>
            <td><?= $item->price()->toFormattedCurrency() ?></td>
        </tr>
    <?php } ?>
</table>
