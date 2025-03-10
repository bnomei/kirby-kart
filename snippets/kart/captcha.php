<div>
    <label>
        <input name="captcha" type="text" value="" placeholder="Captcha" required pattern="[a-zA-Z0-9]{5}">
    </label>
    <figure>
        <img src="<?= kart()->urls()->captcha() ?>" width="150" height="40" alt="Captcha"/>
    </figure>
</div>