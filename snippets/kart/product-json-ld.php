<?php
/** @var ProductPage $product */
?>
<script type="application/ld+json">
    {
        "@context": "https://schema.org/",
        "@type": "Product",
        "name": "<?= $product->title() ?>",
        <?php /* TODO: "image": "?= $product->cover()->url() ?>", */ ?>
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
