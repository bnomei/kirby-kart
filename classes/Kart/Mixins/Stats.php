<?php
/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart\Mixins;

trait Stats
{
    public function stats(): array
    {
        $customers = kirby()->cache('bnomei.kart.stats')->get('customers', 0);
        if (! $customers) {
            // @phpstan-ignore-next-line
            $customers = kirby()->users()
                ->customers()
                ->count();
            kirby()->cache('bnomei.klub.stats')->set('customers', $customers, 15);
        }

        $info = null;
        $theme = 'neutral';

        return [
            'label' => t('bnomei.kart.customers'),
            'value' => $customers,
            'theme' => $theme,
            'info' => $info,
        ];
    }
}
