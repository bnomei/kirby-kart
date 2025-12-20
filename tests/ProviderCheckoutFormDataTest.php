<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

use Bnomei\Kart\Provider;

uses()->group('providers');

beforeEach(function (): void {
    $this->provider = new class(kirby()) extends Provider
    {
        protected string $name = 'stub';

        public function exposeContact(): array
        {
            return $this->checkoutContact();
        }

        public function exposeShipping(): array
        {
            return $this->checkoutShippingAddress();
        }

        public function exposeBilling(): array
        {
            return $this->checkoutBillingAddress();
        }

        public function exposeShippingRate(): ?float
        {
            return $this->checkoutShippingRate();
        }

        public function exposeShippingMethod(): ?string
        {
            return $this->checkoutShippingMethod();
        }

        public function exposeNameParts(): array
        {
            return $this->checkoutNameParts();
        }
    };
});

it('normalizes contact and address fields', function (): void {
    kirby()->session()->set('bnomei.kart.checkout_form_data', [
        'email' => 'ada@example.test',
        'phone' => '+49 30 123456',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'address1' => 'Main Street 1',
        'city' => 'Berlin',
        'postal_code' => '10115',
        'country' => 'de',
        'billing_same_as_shipping' => '1',
    ]);

    $contact = $this->provider->exposeContact();
    $shipping = $this->provider->exposeShipping();
    $billing = $this->provider->exposeBilling();

    expect($contact['email'])->toBe('ada@example.test')
        ->and($contact['phone'])->toBe('+49 30 123456')
        ->and($contact['name'])->toBe('Ada Lovelace')
        ->and($shipping['address1'])->toBe('Main Street 1')
        ->and($shipping['city'])->toBe('Berlin')
        ->and($shipping['postal_code'])->toBe('10115')
        ->and($shipping['country'])->toBe('de')
        ->and($billing['address1'])->toBe('Main Street 1');
});

it('prefers explicit billing fields over shipping', function (): void {
    kirby()->session()->set('bnomei.kart.checkout_form_data', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'address1' => 'Main Street 1',
        'billing_same_as_shipping' => '1',
        'billing_first_name' => 'Blaise',
        'billing_last_name' => 'Pascal',
        'billing_address1' => 'Billing Road 2',
        'billing_city' => 'Paris',
        'billing_postal_code' => '75001',
        'billing_country' => 'fr',
    ]);

    $billing = $this->provider->exposeBilling();

    expect($billing['first_name'])->toBe('Blaise')
        ->and($billing['last_name'])->toBe('Pascal')
        ->and($billing['address1'])->toBe('Billing Road 2')
        ->and($billing['city'])->toBe('Paris')
        ->and($billing['country'])->toBe('fr');
});

it('extracts name parts and shipping meta', function (): void {
    kirby()->session()->set('bnomei.kart.checkout_form_data', [
        'name' => 'Ada Lovelace',
        'shipping_rate' => '9.95',
        'shipping_method' => 'express',
    ]);

    $name = $this->provider->exposeNameParts();

    expect($name['first'])->toBe('Ada')
        ->and($name['last'])->toBe('Lovelace')
        ->and($this->provider->exposeShippingRate())->toBe(9.95)
        ->and($this->provider->exposeShippingMethod())->toBe('express');
});
