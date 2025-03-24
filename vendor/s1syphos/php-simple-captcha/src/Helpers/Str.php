<?php

namespace SimpleCaptcha\Helpers;


/**
 * The String class provides a set
 * of handy methods for string
 * handling and manipulation.
 *
 * @package   Kirby Toolkit
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier GmbH
 * @license   https://opensource.org/licenses/MIT
 */
class Str
{
    /**
     * Checks if a str contains another string
     *
     * @param string $string
     * @param string $needle
     * @param bool $caseInsensitive
     * @return bool
     */
    public static function contains(string $string = null, string $needle, bool $caseInsensitive = false): bool
    {
        if ($needle === '') {
            return true;
        }

        $method = $caseInsensitive === true ? 'stripos' : 'strpos';
        return call_user_func($method, $string ?? '', $needle) !== false;
    }


    /**
     * A UTF-8 safe version of strlen()
     *
     * @param string $string
     * @return int
     */
    public static function length(string $string = null): int
    {
        return mb_strlen($string ?? '', 'UTF-8');
    }


    /**
     * A UTF-8 safe version of strtolower()
     *
     * @param string $string
     * @return string
     */
    public static function lower(string $string = null): string
    {
        return mb_strtolower($string ?? '', 'UTF-8');
    }


    /**
     * Safe ltrim alternative
     *
     * @param string $string
     * @param string $trim
     * @return string
     */
    public static function ltrim(string $string, string $trim = ' '): string
    {
        return preg_replace('!^(' . preg_quote($trim) . ')+!', '', $string);
    }


    /**
     * Returns the position of a needle in a string
     * if it can be found
     *
     * @param string $string
     * @param string $needle
     * @param bool $caseInsensitive
     * @return int|bool
     */
    public static function position(string $string = null, string $needle, bool $caseInsensitive = false)
    {
        if ($caseInsensitive === true) {
            $string = static::lower($string);
            $needle = static::lower($needle);
        }

        return mb_strpos($string, $needle, 0, 'UTF-8');
    }


    /**
     * Safe rtrim alternative
     *
     * @param string $string
     * @param string $trim
     * @return string
     */
    public static function rtrim(string $string, string $trim = ' '): string
    {
        return preg_replace('!(' . preg_quote($trim) . ')+$!', '', $string);
    }


    /**
     * Checks if a string starts with the passed needle
     *
     * @param string $string
     * @param string $needle
     * @param bool $caseInsensitive
     * @return bool
     */
    public static function startsWith(string $string = null, string $needle, bool $caseInsensitive = false): bool
    {
        if ($needle === '') {
            return true;
        }

        return static::position($string, $needle, $caseInsensitive) === 0;
    }


    /**
     * A UTF-8 safe version of substr()
     *
     * @param string $string
     * @param int $start
     * @param int $length
     * @return string
     */
    public static function substr(string $string = null, int $start = 0, int $length = null): string
    {
        return mb_substr($string ?? '', $start, $length, 'UTF-8');
    }


    /**
     * Safe trim alternative
     *
     * @param string $string
     * @param string $trim
     * @return string
     */
    public static function trim(string $string, string $trim = ' '): string
    {
        return static::rtrim(static::ltrim($string, $trim), $trim);
    }
}
