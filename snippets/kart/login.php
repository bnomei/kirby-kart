<form method="POST" action="<?= kart()->login() ?>" class="flex flex-col space-y-2">
    <label>
        <input type="email" name="email" placeholder="Email" required class="w-full border-b border-gcwhite hover:border-gcgray focus:outline-none py-1">
    </label>
    <label>
        <input type="password" name="password" placeholder="Password" required class="w-full border-b border-gcwhite hover:border-gcgray focus:outline-none py-1">
    </label>
    <input type="hidden" name="redirect" value="<?= $page->url() ?>">
    <button type="submit" onclick="this.disabled=true;this.form.submit();" class="cursor-pointer px-3 py-1 bg-kart text-white rounded-md mt-2">Login</button>
</form>
