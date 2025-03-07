<?php use Bnomei\Kart\Kart;

snippet('layout', slots: true) ?>

<?php
/** @var OrderPage $order */
$order ??= $page;
?>

<section>
    <header>
        <h1>Your Order <?= $order->title() ?></h1>
    </header>
    <article>
        <p>
            Invoice Number: #<?= $order->invoiceNumber() ?><br>
            Order Date: <?= $order->paidDate()->toDate('Y-m-d H:i') ?><br>
            Order Status: <?= $order->paymentComplete()->toBool() ? 'paid' : 'open' ?><br>
            Order Total: <?= $order->formattedTotal() ?>
        </p>
        <ol>
            <?php foreach ($order->orderLines() as $line) {
                /** @var \Bnomei\Kart\OrderLine $line */
                /** @var ProductPage $product */
                $product = $line->product();
                ?>
                <li>
                    <a href="<?= $product->url() ?>">
                        <img src="<?= $product->gallery()->toFile()?->url() ?>" alt="<?= $product->title() ?>">
                        <span><?= $product->title() ?></span>
                    </a>
                    <span><?= $line->quantity() ?>x</span>
                    <span><?= Kart::formatCurrency($line->price()) ?></span>
                </li>
            <?php } ?>
        </ol>
    </article>
</section>

