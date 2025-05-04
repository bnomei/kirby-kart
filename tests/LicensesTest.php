<?php

use Bnomei\Kart\Licenses;

it('has a helper to work with licenses', function (): void {
    kirby()->cache('bnomei.kart.licenses')->flush();
    expect(Licenses::class)->toBeString();

    /** @var ProductPage $p */
    $p = kart()->products()->first();
    /** @var OrdersPage $orders */
    $orders = kart()->page('orders');

    $lk = kart()->option('licenses.license.uuid')();
    /** @var OrderPage $o */
    $o = $orders->createOrder([
        'paymentComplete' => true,
        'items' => [
            [
                'key' => [$p->uuid()->toString()],
                'quantity' => 2,
                'price' => $p->price()->toInt() * 2,
                'total' => $p->price()->toInt() * 2 - 3 + 5,
                'subtotal' => $p->price()->toInt() * 2,
                'tax' => 5,
                'discount' => 3,
                'licensekey' => $lk,
            ],
        ],
    ]);

    $lickey = null;
    /** @var \Bnomei\Kart\OrderLine $line */
    foreach ($o->orderLines() as $line) {
        $lickey = $line->licensekey();
        break;
    }

    $l = new Licenses;
    expect($lickey)->toBe($lk)
        ->and($l->order($lickey)->id())->toBe($o->id())
        ->and($l->customer($lickey))->toBeNull()
        ->and($l->activate($lickey))->toBeArray()
        ->and($l->deactivate($lickey))->toBeArray()
        ->and($l->validate($lickey))->toBeArray();
});
