<?php

namespace Bnomei\Kart\Provider;

use Bnomei\Kart\CartLine;
use Bnomei\Kart\ContentPageEnum;
use Bnomei\Kart\Helper;
use Bnomei\Kart\Provider;
use Bnomei\Kart\ProviderEnum;
use Bnomei\Kart\Router;
use Kirby\Toolkit\A;
use Kirby\Uuid\Uuid;

class Kirby extends Provider
{
    protected string $name = ProviderEnum::KIRBY->value;

    public function updatedAt(ContentPageEnum|string|null $sync): string
    {
        return t('kart.now', 'Now');
    }

    public function checkout(): ?string
    {
        $session_id = sha1(Uuid::generate());
        $this->kirby->session()->set('kart.'.$this->name.'.session_id', $session_id);

        return parent::checkout() ? Router::provider_success([
            'session_id' => $session_id,
        ]) : null;
    }

    public function completed(array $data = []): array
    {
        $sessionId = get('session_id');
        if (! $sessionId || $sessionId !== $this->kirby->session()->get('kart.'.$this->name.'.session_id')) {
            return [];
        }

        $input = Helper::sanitize(array_filter([
            'email' => urldecode(get('email', $this->kirby->user()?->email())),
            'payment_method' => urldecode(get('payment_method', '')),
            'payment_status' => urldecode(get('payment_status', '')),
        ]));

        // build data for user, order and stock updates
        $data = array_merge($data, array_filter([
            'email' => strtolower(A::get($input, 'email')),
            'paidDate' => date('Y-m-d H:i:s'),
            'paymentMethod' => A::get($input, 'payment_method'),
            'paymentComplete' => A::get($input, 'payment_status', 'paid') === 'paid',
            'items' => kart()->cart()->lines()->values(fn (CartLine $l) => [
                'key' => [$l->product()?->uuid()->toString()], // pages field expect an array
                'quantity' => $l->quantity(),
                'price' => $l->product()?->price()->toInt(),
                'total' => 0, // TODO:
                'subtotal' => 0, // TODO:
                'tax' => 0, // TODO:
                'discount' => 0, // TODO:
            ]),
        ]));

        $this->kirby->session()->remove('kart.'.$this->name.'.session_id');

        return parent::completed($data);
    }
}
