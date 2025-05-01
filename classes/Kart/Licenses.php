<?php

namespace Bnomei\Kart;

use Kirby\Toolkit\A;

class Licenses
{
    public function validate(string $license_key): array
    {
        $licenses = kirby()->cache('bnomei.kart.licenses')->getOrSet('licenses', function () {
            $data = [];
            /** @var \OrderPage $order */
            foreach (kart()->orders() as $order) {
                foreach ($order->orderLines() as $line) {
                    $data[$line->licenseKey()] = [$line->licenseKey(), $order->uuid()->toString()];
                }
            }

            return $data;
        });

        [$license, $order] = A::get($licenses, $license_key, [null, null]);

        $error = null;
        if (is_null($license)) {
            $error = 'Invalid license key';
        }
        /** @var \OrderPage|null $order */
        $order = $order ? page($order) : null; // find order by stored uuid
        $customer = $order?->customer()->toUser();
        if (is_null($order)) {
            $error = 'Order for license key not found';
        }

        // similar to https://docs.lemonsqueezy.com/api/license-api/validate-license-key
        return [
            'valid' => is_string($license),
            'error' => is_string($error) ? $error : null,
            'license_key' => [
                'key' => $license,
                'created_at' => $order?->paidDate()->toDate('c'), // iso 8601
            ],
            'meta' => $order ? [
                'order_id' => $order->title()->value(),
                'customer_name' => $customer?->name()?->value(),
                'customer_email' => $customer?->email(),
            ] : [],
        ];
    }
}
