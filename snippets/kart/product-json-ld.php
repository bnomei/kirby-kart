<?php
/** @var ProductPage $product */
$product ??= $page;
?>
<script type="application/ld+json">
    {
        "@context": "https://schema.org/",
        "@type": "Product",
        "name": "<?= $product->title() ?>",
        "image": "<?= $product->gallery()->toFile()?->resize(1920)->url() ?>",
        "description": "<?= $product->description()->esc() ?>",
        "url": "<?= $product->url() ?>",
        "offers": {
            "@type": "Offer",
            "price": "<?= $product->price()->toFloat() ?>",
            "priceCurrency": "<?= kart()->currency() ?>",
            "availability": "https://schema.org/<?= $product->stock() > 0 ? 'InStock' : 'OutOfStock' ?>"
        }
    }
</script>
