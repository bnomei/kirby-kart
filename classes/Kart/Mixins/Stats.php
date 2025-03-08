<?php

namespace Bnomei\Kart\Mixins;

trait Stats
{
    public function stats(): array
    {
        $customers = kirby()->cache('bnomei.kart.stats')->get('customers', 0);
        if (! $customers) {
            $customers = kirby()->users()
                ->filterBy('role', 'in', kart()->option('customers.roles'))
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
