<?php

namespace SimpleCaptcha\Helpers;

use SimpleCaptcha\Helpers\Dir;
use SimpleCaptcha\Helpers\Str;

use Exception;


/**
 * The `F` class provides methods for
 * dealing with files on the file system
 * level, like creating, reading,
 * deleting, copying or validatings files.
 *
 * @package   Kirby Filesystem
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier GmbH
 * @license   https://opensource.org/licenses/MIT
 */
class F
{
    /**
     * Just an alternative for dirname() to stay consistent
     *
     * <code>
     *
     * $dirname = F::dirname('/var/www/test.txt');
     * // dirname is /var/www
     *
     * </code>
     *
     * @param string $file The path
     * @return string
     */
    public static function dirname(string $file): string
    {
        return dirname($file);
    }


    /**
     * Checks if the file exists on disk
     *
     * @param string $file
     * @param string $in
     * @return bool
     */
    public static function exists(string $file, string $in = null): bool
    {
        try {
            static::realpath($file, $in);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }


    /**
     * Gets the extension of a file
     *
     * @param string $file The filename or path
     * @param string $extension Set an optional extension to overwrite the current one
     * @return string
     */
    public static function extension(string $file = null, string $extension = null): string
    {
        // overwrite the current extension
        if ($extension !== null) {
            return static::name($file) . '.' . $extension;
        }

        // return the current extension
        return Str::lower(pathinfo($file, PATHINFO_EXTENSION));
    }


    /**
     * Extracts the filename from a file path
     *
     * <code>
     *
     * $filename = F::filename('/var/www/test.txt');
     * // filename is test.txt
     *
     * </code>
     *
     * @param string $name The path
     * @return string
     */
    public static function filename(string $name): string
    {
        return pathinfo($name, PATHINFO_BASENAME);
    }


    /**
     * Checks if the file is writable
     *
     * @param string $file
     * @return bool
     */
    public static function isWritable(string $file): bool
    {
        if (file_exists($file) === false) {
            return is_writable(dirname($file));
        }

        return is_writable($file);
    }



    /**
     * Extracts the name from a file path or filename without extension
     *
     * @param string $name The path or filename
     * @return string
     */
    public static function name(string $name): string
    {
        return pathinfo($name, PATHINFO_FILENAME);
    }


    /**
     * Returns the absolute path to the file if the file can be found.
     *
     * @param string $file
     * @param string $in
     * @return string|null
     */
    public static function realpath(string $file, string $in = null)
    {
        $realpath = realpath($file);

        if ($realpath === false || is_file($realpath) === false) {
            throw new Exception(sprintf('The file does not exist at the given path: "%s"', $file));
        }

        if ($in !== null) {
            $parent = realpath($in);

            if ($parent === false || is_dir($parent) === false) {
                throw new Exception(sprintf('The parent directory does not exist: "%s"', $in));
            }

            if (substr($realpath, 0, strlen($parent)) !== $parent) {
                throw new Exception('The file is not within the parent directory');
            }
        }

        return $realpath;
    }


    /**
     * Deletes a file
     *
     * <code>
     *
     * $remove = F::remove('test.txt');
     * if($remove) echo 'The file has been removed';
     *
     * </code>
     *
     * @param string $file The path for the file
     * @return bool
     */
    public static function remove(string $file): bool
    {
        if (strpos($file, '*') !== false) {
            foreach (glob($file) as $f) {
                static::remove($f);
            }

            return true;
        }

        $file = realpath($file);

        if (file_exists($file) === false) {
            return true;
        }

        return unlink($file);
    }


    /**
     * Creates a new file
     *
     * @param string $file The path for the new file
     * @param mixed $content Either a string, an object or an array. Arrays and objects will be serialized.
     * @param bool $append true: append the content to an existing file if available. false: overwrite.
     * @return bool
     */
    public static function write(string $file, $content, bool $append = false): bool
    {
        if (is_array($content) === true || is_object($content) === true) {
            $content = serialize($content);
        }

        $mode = $append === true ? FILE_APPEND | LOCK_EX : LOCK_EX;

        // if the parent directory does not exist, create it
        if (is_dir(dirname($file)) === false) {
            if (Dir::make(dirname($file)) === false) {
                return false;
            }
        }

        if (static::isWritable($file) === false) {
            throw new Exception('The file "' . $file . '" is not writable');
        }

        return file_put_contents($file, $content, $mode) !== false;
    }
}
