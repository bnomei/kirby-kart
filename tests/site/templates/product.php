<section class="flex">
    <article class="w-2/3 p-4">
        <header>
            <h1><?= $page->title() ?></h1>
            <!-- TODO: image -->
        </header>

        <p><?= $page->description() ?></p>

        <div class="flex space-x-4">
            <?php snippet(option('tests.frontend').'/add') ?>
            <?php snippet(option('tests.frontend').'/wish-or-forget') ?>
        </div>
    </article>
    <aside class="w-1/3 border-l border-gray-100 p-4 space-y-4">
        <?php snippet(option('tests.frontend').'/cart') ?>
        <?php snippet(option('tests.frontend').'/wishlist') ?>
    </aside>
</section>
