<?php
/** @var ProductPage $product */
$product ??= $page;
?>
<script type="application/ld+json">
<?= json_encode(
    $product->productJsonLd(),
    JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_PRETTY_PRINT |
        JSON_PRESERVE_ZERO_FRACTION |
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE |
        JSON_THROW_ON_ERROR,
) ?>
</script>
