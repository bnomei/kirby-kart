<form method="POST" action="<?= kart()->urls()->logout() ?>">
    <button type="submit" onclick="this.disabled=true;this.form.submit();">Logout</button>
</form>
