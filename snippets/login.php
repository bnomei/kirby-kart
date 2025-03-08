<form method="POST" action="<?= kart()->login() ?>">
    <label><input type="email" name="email" value="<?= urldecode(get('email', '')) ?>" placeholder="Email" required></label>
    <label><input type="password" name="password" placeholder="Password" required></label>
    <input type="hidden" name="redirect" value="<?= $page->url() ?>">
    <button type="submit" onclick="this.disabled=true;this.form.submit();">Login</button>
</form>
