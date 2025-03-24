<?php

namespace SimpleCaptcha\Helpers;


/**
 * The `A` class provides a set of handy methods
 * to simplify array handling and make it more
 * consistent. The class contains methods for
 * fetching elements from arrays, merging and
 * sorting or shuffling arrays.
 *
 * @package   Kirby Toolkit
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier GmbH
 * @license   https://opensource.org/licenses/MIT
 */
class A
{
    /**
     * @param mixed $value
     * @param mixed $separator
     * @return string
     */
    public static function join($value, $separator = ', ')
    {
        if (is_string($value) === true) {
            return $value;
        }
        return implode($separator, $value);
    }
}
