<form method="POST" action="<?= kart()->login() ?>">
    <label>
        Email:
        <input type="email" name="email" placeholder="Email" required>
    </label>
    <label>
        Password:
        <input type="password" name="password" placeholder="Password" required>
    </label>
    <button type="submit">Login</button>
</form>
