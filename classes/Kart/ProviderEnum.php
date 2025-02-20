<?php

namespace Bnomei\Kart;

enum ProviderEnum: string
{
    case KIRBY = 'kirby';
    case STRIPE = 'stripe';
    case PADDLE = 'paddle';
    case MOLLIE = 'mollie';
}
