<!DOCTYPE html>
<html lang="<?= kirby()->language()?->code() ?? 'en' ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= page()->isHomePage() ? site()->title() : page()->title().' | '.site()->title() ?></title>
    <style>
        @font-face {
            font-family: "Geist";
            font-weight: 100 900;
            font-display: block;
            src: local("Geist"), url('/assets/fonts/Geist[wght].woff2') format("woff2");
        }
        body {
            font-family: "Geist", sans-serif;
        }
    </style>
</head>
<body>
<?= $slot ?>
</body>
</html>