<?php

namespace Bnomei\Kart;

enum ProviderEnum: string
{
    case FASTSPRING = 'fastspring';
    case GUMROAD = 'gumroad';
    case INVOICENINJA = 'invoiceninja';
    case KIRBY = 'kirbycms';
    case LEMONSQUEEZE = 'lemonsqueeze';
    case MOLLIE = 'mollie';
    case PADDLE = 'paddle';
    case PAYONE = 'payone';
    case PAYPAL = 'paypal';
    case SNIPCART = 'snipcart';
    case STRIPE = 'stripe';
}
