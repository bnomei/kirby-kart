<?php
snippet('kart/kart', slots: true);
// Unless you want to create a custom checkout view you can ignore this template.
// I used it in the online and localhost demo for the fake payment provider.
?>

<main>
    <h1>Fake Payment Provider</h1>
    <nav>
        <a href="<?= \Bnomei\Kart\Router::get('cancel_url') ?>">Back</a>
    </nav>

    <form method="POST" action="<?= \Bnomei\Kart\Router::get('success_url') ?>">
        <?php // TODO: add turnstile protection?>
        <label>
            <input type="email" name="email" placeholder="Email (provide for account creation)" style="min-width: 42ch;"/>
        </label>
        <button type="submit" onclick="this.disabled=true;this.form.submit();">Pay</button>
    </form>
</main>
