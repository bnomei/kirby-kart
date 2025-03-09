<?php
snippet('kart', slots: true);
// COPY and modify the code below this line --------

/** @var OrderPage $order */
$order ??= $page;
?>

<main>
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
                    <span><?= $line->formattedPrice() ?></span>
                </li>
            <?php } ?>
        </ol>
    </article>

    <nav>
        <h3>Previous Orders</h3>

<?php $user = kirby()->user();
if ($user && $user === $page->customer()->toUser()) { ?>
            <ol>
                <?php foreach ($user->orders()->not($order) as $order) { ?>
                    <li><a href="<?= $order->url() ?>"><?= $order->paidDate()->toDate('Y-m-d H:i') ?> <?= $order->title() ?></a></li>
                <?php } ?>
            </ol>
        <?php } else { ?>
            <p >Please log in to see previous orders.</p>
        <?php } ?>
    </nav>
</main>
