<?php
// Unless you want to create a custom checkout view you can ignore this template.
// I used it in my demo for the fake payment provider.
?>

<a href="<?= \Bnomei\Kart\Router::get('cancel_url') ?>">Back</a>

<form method="POST" action="<?= \Bnomei\Kart\Router::get('success_url') ?>">
    <?php // TODO: add turnstile protection?>
    <label>
        <input type="email" name="email" />
    </label>
    <button type="submit" onclick="this.disabled=true;this.form.submit();">Pay</button>
</form>
