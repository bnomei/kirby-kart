<form method="POST" action="<?= kart()->urls()->login() ?>">
    <label><input type="email" name="email" required
                  placeholder="Email"
                  value="<?= urldecode(get('email', '')) ?>"></label>
    <label><input type="password" name="password" placeholder="Password" required></label>
    <?php // TODO: You should add an invisible CAPTCHA here, like...?>
    <?php // snippet('kart/turnstile-form')?>
    <input type="hidden" name="redirect" value="<?= $page->url() ?>">
    <button type="submit" onclick="this.disabled=true;this.form.submit();">Login</button>
</form>
