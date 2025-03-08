<form action="<?= site()->url() ?>/kart/magic-link" method="POST">
    <input type="email" name="email" required
           placeholder="Email"
           value="<?= urldecode(get('email', '')) ?>">
    <input type="hidden" name="redirect" value="<?= $page->url() ?>">
    <input type="hidden" name="token" value="<?= csrf() ?>">
    <button type="submit">Login Link</button>
</form>
