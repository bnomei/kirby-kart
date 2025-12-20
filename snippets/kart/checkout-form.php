<?php
// Example checkout form. Copy into site/snippets and adapt as needed.
// Tip: compute rates/tax in bnomei.kart.checkoutFormData and forward via provider options.

$user = kirby()->user();
$checkout = kart()->checkoutFormData();
$fullName = $user?->name()->value() ?? '';
$nameParts = $fullName !== '' ? explode(' ', $fullName, 2) : ['', ''];
$billingKeys = [
    'billing_first_name',
    'billing_last_name',
    'billing_company',
    'billing_address1',
    'billing_address2',
    'billing_city',
    'billing_state',
    'billing_postal_code',
    'billing_country',
    'billing_phone',
];
$billingSame = true;
foreach ($billingKeys as $key) {
    if (\Kirby\Toolkit\A::get($checkout, $key)) {
        $billingSame = false;
        break;
    }
}
$billingSameValue = \Kirby\Toolkit\A::get($checkout, 'billing_same_as_shipping');
if ($billingSameValue !== null && $billingSameValue !== '') {
    $billingSame = in_array(strtolower((string) $billingSameValue), ['1', 'true', 'yes', 'on'], true);
}
$prefill = function (string $key, string $fallback = '') use ($checkout): string {
    $value = \Kirby\Toolkit\A::get($checkout, $key, $fallback);

    return esc((string) $value);
};
?>

<form method="POST" action="<?= kart()->urls()->cart_checkout() ?>">
    <?php snippet('kart/input-csrf') ?>

    <fieldset>
        <legend>Contact</legend>
        <label>
            <input type="email" name="email" required
                   autocomplete="email"
                   placeholder="Email"
                   value="<?= $prefill('email', $user?->email() ?? '') ?>">
        </label>
        <label>
            <input type="tel" name="phone"
                   autocomplete="tel"
                   placeholder="Phone"
                   value="<?= $prefill('phone') ?>">
        </label>
        <label>
            <input type="text" name="first_name" required
                   autocomplete="given-name"
                   placeholder="First name"
                   value="<?= $prefill('first_name', $nameParts[0] ?? '') ?>">
        </label>
        <label>
            <input type="text" name="last_name" required
                   autocomplete="family-name"
                   placeholder="Last name"
                   value="<?= $prefill('last_name', $nameParts[1] ?? '') ?>">
        </label>
    </fieldset>

    <fieldset>
        <legend>Shipping</legend>
        <label>
            <input type="text" name="company"
                   autocomplete="organization"
                   placeholder="Company"
                   value="<?= $prefill('company') ?>">
        </label>
        <label>
            <input type="text" name="address1" required
                   autocomplete="shipping address-line1"
                   placeholder="Address line 1"
                   value="<?= $prefill('address1') ?>">
        </label>
        <label>
            <input type="text" name="address2"
                   autocomplete="shipping address-line2"
                   placeholder="Address line 2"
                   value="<?= $prefill('address2') ?>">
        </label>
        <label>
            <input type="text" name="city" required
                   autocomplete="shipping address-level2"
                   placeholder="City"
                   value="<?= $prefill('city') ?>">
        </label>
        <label>
            <input type="text" name="state"
                   autocomplete="shipping address-level1"
                   placeholder="State / Province"
                   value="<?= $prefill('state') ?>">
        </label>
        <label>
            <input type="text" name="postal_code" required
                   autocomplete="shipping postal-code"
                   placeholder="Postal code"
                   value="<?= $prefill('postal_code') ?>">
        </label>
        <label>
            <input type="text" name="country" required
                   autocomplete="shipping country"
                   placeholder="Country (ISO-2)"
                   value="<?= $prefill('country') ?>">
        </label>
        <label>
            <select name="shipping_method">
                <option value="standard" <?= $prefill('shipping_method') === 'standard' ? 'selected' : '' ?>>Standard</option>
                <option value="express" <?= $prefill('shipping_method') === 'express' ? 'selected' : '' ?>>Express</option>
            </select>
            Shipping method
        </label>
    </fieldset>

    <fieldset>
        <legend>Billing</legend>
        <label>
            <input type="checkbox" name="billing_same_as_shipping" value="1" <?= $billingSame ? 'checked' : '' ?> data-billing-same>
            Billing address is the same as shipping
        </label>
        <div data-billing-fields>
            <label>
                <input type="text" name="billing_first_name"
                       autocomplete="billing given-name"
                       placeholder="First name"
                       value="<?= $prefill('billing_first_name') ?>">
            </label>
            <label>
                <input type="text" name="billing_last_name"
                       autocomplete="billing family-name"
                       placeholder="Last name"
                       value="<?= $prefill('billing_last_name') ?>">
            </label>
            <label>
                <input type="text" name="billing_company"
                       autocomplete="billing organization"
                       placeholder="Company"
                       value="<?= $prefill('billing_company') ?>">
            </label>
            <label>
                <input type="text" name="billing_address1"
                       autocomplete="billing address-line1"
                       placeholder="Address line 1"
                       value="<?= $prefill('billing_address1') ?>">
            </label>
            <label>
                <input type="text" name="billing_address2"
                       autocomplete="billing address-line2"
                       placeholder="Address line 2"
                       value="<?= $prefill('billing_address2') ?>">
            </label>
            <label>
                <input type="text" name="billing_city"
                       autocomplete="billing address-level2"
                       placeholder="City"
                       value="<?= $prefill('billing_city') ?>">
            </label>
            <label>
                <input type="text" name="billing_state"
                       autocomplete="billing address-level1"
                       placeholder="State / Province"
                       value="<?= $prefill('billing_state') ?>">
            </label>
            <label>
                <input type="text" name="billing_postal_code"
                       autocomplete="billing postal-code"
                       placeholder="Postal code"
                       value="<?= $prefill('billing_postal_code') ?>">
            </label>
            <label>
                <input type="text" name="billing_country"
                       autocomplete="billing country"
                       placeholder="Country (ISO-2)"
                       value="<?= $prefill('billing_country') ?>">
            </label>
            <label>
                <input type="tel" name="billing_phone"
                       autocomplete="billing tel"
                       placeholder="Phone"
                       value="<?= $prefill('billing_phone') ?>">
            </label>
        </div>
    </fieldset>

    <label>
        <textarea name="note" placeholder="Order note"><?= $prefill('note') ?></textarea>
    </label>

    <input type="hidden" name="redirect" value="<?= $page?->url() ?>">
    <button type="submit" onclick="this.disabled=true;this.form.submit();">Checkout</button>
</form>

<script>
    (() => {
        const checkbox = document.querySelector('[data-billing-same]');
        const fields = document.querySelector('[data-billing-fields]');
        if (!checkbox || !fields) return;
        const toggle = () => { fields.hidden = checkbox.checked; };
        checkbox.addEventListener('change', toggle);
        toggle();
    })();
</script>
