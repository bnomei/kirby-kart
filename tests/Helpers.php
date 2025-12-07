<?php

use Kirby\Cms\User;
use Bnomei\Kart\Cart;
use Kirby\Cms\Collection;

/**
 * Shared test helpers for provider flows.
 */
function findOrCreateTestUser(string $email = 'b@bnomei.com'): User
{
    $user = kirby()->users()->findBy('email', $email);
    if (! $user) {
        kirby()->impersonate('kirby');
        $user = kirby()->users()->create([
            'email' => $email,
            'role' => 'customer',
            'password' => bin2hex(random_bytes(16)),
        ]);
    }

    $user->loginPasswordless();

    return $user;
}

if (! class_exists('KartTestCartStub')) {
    /**
     * Minimal cart stub that lets provider tests bypass real cart state.
     */
    class KartTestCartStub extends Cart
    {
        public function __construct(private float $stubSubtotal, private array $stubLines = [])
        {
            parent::__construct('cart', []);
        }

        public function lines(): Collection
        {
            return new Collection($this->stubLines);
        }

        public function subtotal(): float
        {
            return $this->stubSubtotal;
        }

        public function hash(): string
        {
            return 'test-hash';
        }
    }
}

/**
 * Replace the global cart singleton with a lightweight stub.
 */
function injectStubCart(float $subtotal = 1.23, array $lines = []): Cart
{
    $cart = new KartTestCartStub($subtotal, $lines);

    (function ($cart) {
        $this->cart = $cart;
    })->call(kart(), $cart);

    return $cart;
}
