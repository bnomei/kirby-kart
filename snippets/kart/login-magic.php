<form action="<?= kart()->urls()->magiclink() ?>" method="POST">
    <label><input type="email" name="email" required
                  placeholder="Email"
                  value="<?= urldecode(get('email', '')) ?>"></label>
    <?php // TODO: You should add an CAPTCHA here, like...?>
    <?php // snippet('kart/turnstile-form') // or?>
    <?php snippet('kart/captcha')  ?>
    <input type="hidden" name="redirect" value="<?= $page->url() ?>">
    <button type="submit">Login Link</button>
</form>
