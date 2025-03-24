<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Closure;
use Kirby\Data\Json;
use Kirby\Filesystem\F;
use Kirby\Http\Remote;
use Kirby\Plugin\License as KirbyLicense;
use Kirby\Plugin\LicenseStatus;
use Kirby\Plugin\Plugin;

use function kirby;
use function option;
use function t;

class License extends KirbyLicense
{
    const NAME = 'Kirby Kart';

    private string $licenseFile;

    private mixed $licenseKey;

    private bool $isLocal;

    private array $license;

    public function __construct(
        protected Plugin $plugin,
        protected string $name,
        protected ?string $link = null,
        ?LicenseStatus $status = null
    ) {
        $this->isLocal = kirby()->environment()->isLocal();
        $this->licenseKey = option('bnomei.kart.license'); // from options NOT from file
        if ($this->licenseKey instanceof Closure) {
            $v = $this->licenseKey;
            $this->licenseKey = $v();
        }
        $this->licenseFile = kirby()->roots()->license().'.kart';
        $this->license = $this->readLicense();

        $status = $this->detectStatus();
        parent::__construct(
            $plugin,
            $status->label(),
            $status->value() !== 'active' ? 'https://buy-kart.bnomei.com' : null,
            $status
        );
    }

    public function detectStatus(): LicenseStatus
    {
        $status = $this->validate();
        if ($status) {
            return $status;
        }

        $status = $this->activate();
        if ($status) {
            return $status;
        }

        if ($this->isLocal) {
            return new LicenseStatus(
                value: 'missing',
                icon: 'cart',
                label: t('license.buy'),
                theme: 'love',
            );
        }

        return new LicenseStatus(
            value: 'missing',
            icon: 'alert',
            label: t('license.status.missing.info').': '.t('license.buy').'!',
            theme: 'negative',
        );
    }

    public function activate(): ?LicenseStatus
    {
        if (! empty($this->license) || empty($this->licenseKey)) {
            return null;
        }

        $response = $this->api('activate', [
            'license_key' => $this->licenseKey,
            'instance_name' => static::NAME,
        ]);

        if ($response->code() !== 200) {
            return null;
        }

        $json = $response->json();
        if ($json['activated'] !== true ||
            $json['meta']['product_name'] !== static::NAME
        ) {
            return null;
        }

        $this->license = $json;
        $this->writeLicense($this->license);

        return new LicenseStatus(
            value: 'active',
            icon: 'check',
            label: t('license.status.active.label').': '.$this->license['meta']['customer_name'],
            theme: 'positive',
        );
    }

    public function deactivate(): ?LicenseStatus
    {
        if (empty($this->license) || empty($this->licenseKey)) {
            return null;
        }

        $response = $this->api('deactivate', [
            'license_key' => $this->license['license_key']['key'],
            'instance_id' => $this->license['instance']['id'],
        ]);

        if ($response->code() !== 200) {
            return null;
        }

        $json = $response->json();
        if ($json['deactivated'] !== true ||
            $json['meta']['product_name'] !== static::NAME) {
            return null;
        }

        $this->license = [];
        F::remove($this->licenseFile);

        return null;
    }

    public function validate(): ?LicenseStatus
    {
        if (empty($this->license) || empty($this->licenseKey)) {
            return null;
        }

        $response = $this->api('validate', [
            'license_key' => $this->license['license_key']['key'],
            'instance_id' => $this->license['instance']['id'],
        ]);

        if ($response->code() !== 200) {
            return null;
        }

        $json = $response->json();
        if (! $json['valid'] || $json['meta']['product_name'] !== static::NAME) {
            return null;
        }

        $this->license = $json;

        return new LicenseStatus(
            value: 'active',
            icon: 'check',
            label: t('license.status.active.label').': '.$this->license['meta']['customer_name'],
            theme: 'positive',
        );
    }

    protected function api(string $action, array $data): Remote
    {
        return Remote::post(
            'https://api.lemonsqueezy.com/v1/licenses/'.$action,
            [
                'headers' => [
                    'Accept: application/json',
                    'Content-Type: application/x-www-form-urlencoded',
                ],
                'data' => $data,
            ]
        );
    }

    private function readLicense(): array
    {
        if (! F::exists($this->licenseFile)) {
            return [];
        }
        $data = Json::read($this->licenseFile);
        $decrypted = static::dec($data['encrypted']);

        return $data['hash'] === static::hash($decrypted) ? $decrypted : [];
    }

    private function writeLicense(array $data): bool
    {
        return Json::write($this->licenseFile, [
            'encrypted' => static::enc($data),
            'hash' => static::hash($data),
        ]);
    }

    private static function enc(array $d): string
    {
        $iv = random_bytes($l = openssl_cipher_iv_length('aes-256-cbc'));

        return base64_encode($iv.openssl_encrypt(json_encode($d), 'aes-256-cbc', self::NAME, 0, $iv));
    }

    private static function dec(string $d): ?array
    {
        $d = base64_decode($d);
        $iv = substr($d, 0, $l = openssl_cipher_iv_length('aes-256-cbc'));

        return ($j = openssl_decrypt(substr($d, $l), 'aes-256-cbc', self::NAME, 0, $iv)) ? json_decode($j, true) : null;
    }

    private static function hash(array $data): string
    {
        return hash('sha256', json_encode($data));
    }
}
