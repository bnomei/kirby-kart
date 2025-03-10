<?php if (get('status') == 'sent') { ?>
    <p>Login link was sent. Check your inbox.</p>
<?php } else { ?>
    <form action="<?= kart()->urls()->magiclink() ?>" method="POST">
        <label><input type="email" name="email" required
                      placeholder="Email"
                      value="<?= urldecode(get('email', '')) ?>"></label>
        <?php // TODO: You should add an CAPTCHA here, like...?>
        <?php // snippet('kart/turnstile-form') // or?>
        <?php snippet('kart/captcha')  ?>
        <input type="hidden" name="redirect" value="<?= url(\Bnomei\Kart\Router::LOGIN) ?>?status=sent">
        <input type="hidden" name="success_url" value="<?= url(\Bnomei\Kart\Router::KART) ?>?status=welcome-back">
        <button type="submit">Login Link</button>
    </form>
<?php } ?>
