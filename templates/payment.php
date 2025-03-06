
<a href="<?= \Bnomei\Kart\Router::get('cancel_url') ?>">Back</a>

<form method="POST" action="<?= \Bnomei\Kart\Router::get('success_url') ?>">
    <label>
        <input type="email" name="email" />
    </label>
    <button type="submit">Pay</button>
</form>
