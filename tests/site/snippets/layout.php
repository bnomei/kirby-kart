<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $page->title() ?> | <?= site()->title() ?></title>
</head>
<body>
    <nav>
        <ul>
        <?php foreach (site()->breadcrumb() as $crumb) { ?>
            <li><a href="<?= $crumb->url() ?>"><?= $crumb->title() ?></a></li>
        <?php } ?>
            <li><a href="/cart">Cart (<?= kart()->cart()->quantity() ?>)</a></li>
        </ul>
    </nav>
    <main>
        <?= $slot ?>
    </main>
</body>
</html>
