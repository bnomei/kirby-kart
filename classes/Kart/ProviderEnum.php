<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

enum ProviderEnum: string
{
    case CHECKOUT = 'checkout';
    case FASTSPRING = 'fastspring';
    case GUMROAD = 'gumroad';
    case INVOICE_NINJA = 'invoice_ninja';
    case KIRBY = 'kirby_cms';
    case LEMONSQUEEZY = 'lemonsqueezy';
    case MOLLIE = 'mollie';
    case PADDLE = 'paddle';
    case PAYPAL = 'paypal';
    case PAYONE = 'payone';
    case SHOPIFY = 'shopify';
    case SQUARE = 'square';
    case SNIPCART = 'snipcart';
    case STRIPE = 'stripe';
    case SUMUP = 'sumup';
}
