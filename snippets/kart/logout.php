<form method="POST" action="<?= kart()->logout() ?>">
    <button type="submit" onclick="this.disabled=true;this.form.submit();" class="cursor-pointer text-xs px-2 py-1 border border-kart text-kart rounded-md">Logout</button>
</form>
