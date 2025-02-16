<?php
$hash = md5(strtolower(trim($email)));
$size ??= 200;
?>
<img class="rounded-full w-12 h-12" src="<?= "https://www.gravatar.com/avatar/{$hash}?s={$size}" ?>" alt="<?= $name ?? $email ?>">
