#!/usr/bin/env bash
# Run from the repository root, after composer testBefore, in a disposable orb.
set -euo pipefail

run=$(openssl rand -hex 6)
service="kart-$run"
export SERVER_PORT=8786 HTTP_HOST=localhost:8786
url="http://$HTTP_HOST/panel"
slug="a-gallery-check-$run"
email="$slug@example.test"
export slug email
b() { agent-browser --session "$service" "$@"; }
fixture() { php -r 'require "tests/index.php"; kirby()->impersonate("kirby"); '"$1"; }

# Don't reuse another server or send test credentials outside the orb.
if (echo >/dev/tcp/127.0.0.1/8786) 2>/dev/null; then echo 'Panel smoke port 8786 is in use' >&2; exit 1; fi
amp orb service start "$service" --port "$SERVER_PORT" --command 'php -S 127.0.0.1:$PORT -t tests tests/kirby/router.php'
cleanup() {
    b close >/dev/null || true
    amp orb service stop "$service" >/dev/null
    fixture 'page("products/".getenv("slug"))?->delete(true); kirby()->user(getenv("email"))?->delete();'
}
trap cleanup EXIT
password=$(openssl rand -hex 24)
password="$password" fixture 'if (page("products")?->children()->count() !== 12 || kirby()->users()->isEmpty()) {
    throw new RuntimeException("Run composer testBefore in the disposable test site first");
}
kirby()->users()->create(["role" => "admin", "email" => getenv("email"), "password" => getenv("password")]);'
b open "$url"
b set viewport 1280 1000 2
b wait 'input[type=email]'
b fill 'input[type=email]' "$email"
b fill 'input[type=password]' "$password" >/dev/null
b find role button click --name 'Log in' --exact
b wait --url '**/panel/site'
b open "$url/pages/products"
b wait --text 'Categories (4)'
b wait --text 'Tags (19)'
b wait --text 'Variants (0)'
b wait --text 'super-villains'

fixture '$page = page("products")->createChild([
    "slug" => getenv("slug"), "template" => "product", "content" => [
        "title" => ucfirst(str_replace("-", " ", getenv("slug"))), "price" => 12,
        "variants" => [["variant" => "color:blue, size:large", "price" => 12], ["variant" => "color:red, size:small", "price" => 10]],
    ],
]);
foreach (["red" => [195, 61, 66], "blue" => [49, 93, 190]] as $name => $rgb) {
    $image = imagecreatetruecolor(360, 240);
    imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
    $source = tmpfile();
    imagepng($image, $source);
    $files[$name] = $page->createFile(["source" => stream_get_meta_data($source)["uri"], "filename" => "$name.png"])->uuid()->toString();
}
$page->update(["gallery" => [$files["blue"], $files["red"]]]);'
b reload
b wait --text 'Variants (4)'
b wait --text 'size:small'
b wait --fn 'Array.from(document.images).some(i => i.currentSrc.includes("/blue-") && i.complete && i.naturalWidth > 0)'
if [[ -n ${PANEL_SCREENSHOT:-} ]]; then b screenshot "$PANEL_SCREENSHOT"; fi
b open "$url/pages/products+$slug"
b wait 'input[name=price]'
b fill 'input[name=price]' '18.75'
b press Tab
b find role button click --name Save --exact
b wait --fn '!Array.from(document.querySelectorAll("button")).some(b => b.innerText.trim() === "Save")'
fixture 'if (page("products/".getenv("slug"))->price()->toFloat() !== 18.75) throw new RuntimeException("Price was not saved");'
b reload
b wait --fn 'document.querySelector("input[name=price]")?.valueAsNumber === 18.75'
b open "$url/pages/stocks"
b wait --text 'Leonardo'
b open "$url/pages/orders"
b wait --text 'Revenue (30 days)'
echo 'Panel: catalog fields, gallery order, price save/reload, stocks and orders passed.'
