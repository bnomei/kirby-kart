<!DOCTYPE html>
<html lang="<?= kirby()->language()?->code() ?? 'en' ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= page()->isHomePage() ? site()->title() : page()->title().' | '.site()->title() ?></title>
</head>
<body>
<?= $slot ?>
</body>
</html>