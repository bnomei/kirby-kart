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
    case FASTSPRING = 'fastspring';
    case GUMROAD = 'gumroad';
    case INVOICE_NINJA = 'invoice_ninja';
    case KIRBY = 'kirby_cms';
    case LEMONSQUEEZE = 'lemonsqueeze';
    case MOLLIE = 'mollie';
    case PADDLE = 'paddle';
    case PAYONE = 'payone';
    case PAYPAL = 'paypal';
    case SNIPCART = 'snipcart';
    case STRIPE = 'stripe';
}
