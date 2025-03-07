<form method="POST" action="<?= kart()->logout() ?>">
    <button type="submit" onclick="this.disabled=true;this.form.submit();">Logout</button>
</form>
