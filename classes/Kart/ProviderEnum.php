<?php

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
