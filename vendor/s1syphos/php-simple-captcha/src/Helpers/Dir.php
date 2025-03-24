<?php

namespace SimpleCaptcha\Helpers;

use SimpleCaptcha\Helpers\F;

use Exception;


/**
 * The `Dir` class provides methods
 * for dealing with directories on the
 * file system level, like creating,
 * listing, moving, copying or
 * evaluating directories etc.
 *
 * For the Cms, it includes methods,
 * that handle scanning directories
 * and converting the results into
 * children, files and other page stuff.
 *
 * @package   Kirby Filesystem
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier GmbH
 * @license   https://opensource.org/licenses/MIT
 */
class Dir
{
    /**
     * Ignore when scanning directories
     *
     * @var array
     */
    public static $ignore = [
        '.',
        '..',
        '.DS_Store',
        '.gitignore',
        '.git',
        '.svn',
        '.htaccess',
        'Thumb.db',
        '@eaDir'
    ];


    /**
     * Get all files
     *
     * @param string $dir
     * @param array $ignore
     * @param bool $absolute
     * @return array
     */
    public static function files(string $dir, array $ignore = null, bool $absolute = false): array
    {
        $result = array_values(array_filter(static::read($dir, $ignore, true), 'is_file'));

        if ($absolute !== true) {
            $result = array_map('basename', $result);
        }

        return $result;
    }


    /**
     * Creates a new directory
     *
     * @param string $dir The path for the new directory
     * @param bool $recursive Create all parent directories, which don't exist
     * @return bool True: the dir has been created, false: creating failed
     * @throws \Exception If a file with the provided path already exists or the parent directory is not writable
     */
    public static function make(string $dir, bool $recursive = true): bool
    {
        if (empty($dir) === true) {
            return false;
        }

        if (is_dir($dir) === true) {
            return true;
        }

        if (is_file($dir) === true) {
            throw new Exception(sprintf('A file with the name "%s" already exists', $dir));
        }

        $parent = dirname($dir);

        if ($recursive === true) {
            if (is_dir($parent) === false) {
                static::make($parent, true);
            }
        }

        if (is_writable($parent) === false) {
            throw new Exception(sprintf('The directory "%s" cannot be created', $dir));
        }

        return mkdir($dir);
    }


    /**
     * Reads all files from a directory and returns them as an array.
     * It skips unwanted invisible stuff.
     *
     * @param string $dir The path of directory
     * @param array $ignore Optional array with filenames, which should be ignored
     * @param bool $absolute If true, the full path for each item will be returned
     * @return array An array of filenames
     */
    public static function read(string $dir, array $ignore = null, bool $absolute = false): array
    {
        if (is_dir($dir) === false) {
            return [];
        }

        // create the ignore pattern
        $ignore ??= static::$ignore;
        $ignore   = array_merge($ignore, ['.', '..']);

        // scan for all files and dirs
        $result = array_values((array)array_diff(scandir($dir), $ignore));

        // add absolute paths
        if ($absolute === true) {
            $result = array_map(fn ($item) => $dir . '/' . $item, $result);
        }

        return $result;
    }


    /**
     * Removes a folder including all containing files and folders
     *
     * @param string $dir
     * @return bool
     */
    public static function remove(string $dir): bool
    {
        $dir = realpath($dir);

        if (is_dir($dir) === false) {
            return true;
        }

        if (is_link($dir) === true) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $childName) {
            if (in_array($childName, ['.', '..']) === true) {
                continue;
            }

            $child = $dir . '/' . $childName;

            if (is_link($child) === true) {
                unlink($child);
            } elseif (is_dir($child) === true) {
                static::remove($child);
            } else {
                F::remove($child);
            }
        }

        return rmdir($dir);
    }
}
