<?php

namespace Bnomei\Kart;

use Closure;
use Kirby\Toolkit\Str;
use Kirby\Toolkit\SymmetricCrypto;
use Kirby\Uuid\Uuid;
use NumberFormatter;

class Helper
{
    public static function sanitize(mixed $data, bool $checkLength = true): mixed
    {
        if (! is_string($data) && ! is_array($data)) {
            return false;
        }

        // convert to json and limit amount of chars with exception
        $json = is_array($data) ? json_encode($data) : $data;
        if ($json === false) {
            return false;
        }
        if (strlen($json) > 10000) {
            return false;
        }
        $json = null; // free memory

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                // checkLength of total data was already done above
                $data[$key] = static::sanitize($value, checkLength: false);
            }
        } elseif (is_string($data)) {
            $data = strip_tags(trim(empty($data) ? '' : $data));
        }

        return $data;
    }

    public static function formatNumber(float $number, bool $prefix = false): string
    {
        if ($prefix) {
            $prefix = $number > 0 ? '+' : ''; // - will be in format anyway
        } else {
            $prefix = '';
        }

        return $prefix.self::formatter(NumberFormatter::DECIMAL)->format($number);
    }

    private static function formatter(?int $style = null): NumberFormatter
    {
        $kirby = kirby();
        $locale = $kirby->multilang() ? $kirby->language()?->locale() : null;
        if (is_array($locale)) {
            $locale = $locale[0];
        }
        if (is_null($locale)) {
            $locale = $kirby->option('bnomei.kart.locale', 'en_EN');
        }

        if (! $kirby->environment()->isLocal() && $kirby->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
            $locale = 'ja_JP';
        }

        return new NumberFormatter($locale, $style ?? NumberFormatter::DECIMAL);
    }

    public static function formatCurrency(float $number): string
    {
        $kirby = kirby();
        $currency = strval($kirby->option('bnomei.kart.currency', 'EUR'));

        if (! $kirby->environment()->isLocal() && $kirby->plugin('bnomei/kart')->license()->status()->value() !== 'active') {
            $currency = 'JPY';
        }

        return self::formatter(NumberFormatter::CURRENCY)->formatCurrency($number, $currency) ?: '';
    }

    public static function nonAmbiguousUuid(int $length): string
    {
        return str_replace(
            ['o', 'O', 'l', 'L', 'I', 'i', 'B', 'S', 's'],
            ['0', '0', '1', '1', '1', '1', '8', '5', '5'],
            Uuid::generate($length)
        );
    }

    public static function encrypt(mixed $data, ?string $password = null, bool $json = false): string
    {
        $password ??= option('crypto.password');
        if ($password instanceof Closure) {
            $password = $password();
        }
        if ($password && is_string($password) && SymmetricCrypto::isAvailable()) {
            $encr = new SymmetricCrypto(password: $password);
            if ($json || is_array($data)) {
                $data = json_encode($data) ?: '';
            }
            $data = is_string($data) ? $encr->encrypt($data) : $data;
        }

        return base64_encode(strval($data));
    }

    public static function decrypt(string $data, Closure|string|null $password = null, bool $json = false): mixed
    {
        $data = base64_decode($data);

        $password ??= option('router.encryption');
        if ($password instanceof Closure) {
            $password = $password();
        }
        if ($password && SymmetricCrypto::isAvailable()) {
            $encr = new SymmetricCrypto(password: $password);
            if (Str::contains($data, '"mode":"secretbox"')) {
                $data = $encr->decrypt($data);
            }
            if ($json) {
                $data = json_decode($data, true);
            }
        }

        return $data;
    }
}
