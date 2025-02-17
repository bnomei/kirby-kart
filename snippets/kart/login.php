<form method="POST" action="<?= kart()->login() ?>" class="flex flex-col space-y-2">
    <label>
        <input type="email" name="email" placeholder="Email" required class="w-full">
    </label>
    <label>
        <input type="password" name="password" placeholder="Password" required class="w-full">
    </label>
    <button type="submit" class="cursor-pointer px-3 py-1 bg-kart text-white rounded-md">Login</button>
</form>
