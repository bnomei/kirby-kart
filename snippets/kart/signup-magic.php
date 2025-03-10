<form action="<?= kart()->urls()->signup_magic() ?>" method="POST">
    <label><input type="email" name="email" required
           placeholder="Email"
           value="<?= urldecode(get('email', '')) ?>"></label>
    <label><input type="text" name="name" required
           placeholder="Name"
           value="<?= get('name') ?>"></label>
    <?php // TODO: You should add a CAPTCHA here, like...?>
    <?php // snippet('kart/turnstile-form') // or?>
    <?php snippet('kart/captcha') ?>
    <input type="hidden" name="redirect" value="<?= site()->url() ?>">
    <input type="hidden" name="success_url"
           value="<?= site()->url() ?>?status=welcome">
    <button type="submit">Signup with magic link</button>
</form>
