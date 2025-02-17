<?php

namespace Bnomei\Kart;

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

    private static function formatter($style): NumberFormatter
    {
        $kirby = kirby();
        $locale = $kirby->multilang() ? $kirby->language()->locale() : null;
        if (is_array($locale)) {
            $locale = $locale[0];
        }
        if (is_null($locale)) {
            $locale = $kirby->option('bnomei.kart.locale', 'en_EN');
        }

        return new NumberFormatter($locale, $style ?? NumberFormatter::DECIMAL);
    }

    public static function formatNumber(float $number): string
    {
        return self::formatter(NumberFormatter::DECIMAL)->format($number);
    }

    public static function formatCurrency(float $number): string
    {
        $currency = kirby()->option('bnomei.kart.currency', 'EUR');

        return self::formatter(NumberFormatter::CURRENCY)->formatCurrency($number, $currency);
    }

    public static function uuid(int $length): string
    {
        return str_replace(
            ['o', 'O', 'l', 'L', 'I', 'i', 'B', 'S', 's'],
            ['0', '0', '1', '1', '1', '1', '8', '5', '5'],
            \Kirby\Uuid\Uuid::generate($length)
        );
    }
}
