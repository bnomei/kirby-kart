<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

enum ContentPageEnum: string
{
    case ORDERS = 'orders';
    case PRODUCTS = 'products';
    case STOCKS = 'stocks';
}
