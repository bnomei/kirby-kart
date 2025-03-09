<form action="<?= \Bnomei\Kart\Router::signup_magic() ?>" method="POST">
    <?php // TODO: You should add a CAPTCHA here?>
    <input type="hidden" name="token" value="<?= csrf() ?>">
    <input type="hidden" name="redirect" value="<?= site()->url() ?>">
    <input type="email" name="email" required
           placeholder="Email"
           value="<?= urldecode(get('email', '')) ?>">
    <input type="text" name="name" required
           placeholder="Name"
           value="<?= get('name') ?>">
    <input type="hidden" name="success_url"
           value="<?= site()->url() ?>?status=welcome">
    <button type="submit">Signup with magic link</button>
</form>
