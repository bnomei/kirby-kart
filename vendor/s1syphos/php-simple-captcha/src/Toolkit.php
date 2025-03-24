<?php

declare(strict_types=1);

namespace SimpleCaptcha;

use Exception;

/**
 * Class Toolkit
 *
 * Utilities for manipulating & converting various input
 */
class Toolkit
{
    /**
     * Converts color values from HEX to RGB
     *
     * See https://stackoverflow.com/a/31934345
     *
     * @param string $color HEX color
     * @return array RGB values
     * @throws \Exception Invalid HEX color provided
     */
    public static function hex2rgb(string $color): array
    {
        $hex = str_replace('#', '', $color);
        $length = strlen($hex);

        if ($length == 3 && preg_match('/^[a-f0-9]{3}$/i', $hex)) {
            return [
                hexdec(str_repeat(substr($hex, 0, 1), 2)),
                hexdec(str_repeat(substr($hex, 1, 1), 2)),
                hexdec(str_repeat(substr($hex, 2, 1), 2)),
            ];
        }

        if ($length == 6 && preg_match('/^[a-f0-9]{6}$/i', $hex)) {
            return [
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2)),
            ];
        }

        throw new Exception(sprintf('Invalid HEX color: "%s"', $color));
    }


    /**
     * Converts color code to RGB values
     *
     * @param int $code Color code
     * @return array RGB values
     */
    public static function int2rgb(int $code): array
    {
        return [
            (int) ($code >> 16) & 0xff,
            (int) ($code >> 8) & 0xff,
            (int) ($code) & 0xff,
        ];
    }
}
