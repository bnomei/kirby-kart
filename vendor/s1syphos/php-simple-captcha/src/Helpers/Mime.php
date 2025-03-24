<?php

namespace SimpleCaptcha\Helpers;

use SimpleCaptcha\Helpers\F;


/**
 * The `Mime` class provides method
 * for MIME type detection or guessing
 * from different criteria like
 * extensions etc.
 *
 * @package   Kirby Filesystem
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier GmbH
 * @license   https://opensource.org/licenses/MIT
 */
class Mime
{
    /**
     * Extension to MIME type map
     *
     * @var array
     */
    public static $types = [
        'ai'    => 'application/postscript',
        'aif'   => 'audio/x-aiff',
        'aifc'  => 'audio/x-aiff',
        'aiff'  => 'audio/x-aiff',
        'avi'   => 'video/x-msvideo',
        'avif'   => 'image/avif',
        'bmp'   => 'image/bmp',
        'css'   => 'text/css',
        'csv'   => ['text/csv', 'text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream'],
        'doc'   => 'application/msword',
        'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'dotx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
        'dvi'   => 'application/x-dvi',
        'eml'   => 'message/rfc822',
        'eps'   => 'application/postscript',
        'exe'   => ['application/octet-stream', 'application/x-msdownload'],
        'gif'   => 'image/gif',
        'gtar'  => 'application/x-gtar',
        'gz'    => 'application/x-gzip',
        'htm'   => 'text/html',
        'html'  => 'text/html',
        'ico'   => 'image/x-icon',
        'ics'   => 'text/calendar',
        'js'    => 'application/x-javascript',
        'json'  => ['application/json', 'text/json'],
        'j2k'   => ['image/jp2'],
        'jp2'   => ['image/jp2'],
        'jpg'   => ['image/jpeg', 'image/pjpeg'],
        'jpeg'  => ['image/jpeg', 'image/pjpeg'],
        'jpe'   => ['image/jpeg', 'image/pjpeg'],
        'log'   => ['text/plain', 'text/x-log'],
        'm4a'   => 'audio/mp4',
        'm4v'   => 'video/mp4',
        'mid'   => 'audio/midi',
        'midi'  => 'audio/midi',
        'mif'   => 'application/vnd.mif',
        'mov'   => 'video/quicktime',
        'movie' => 'video/x-sgi-movie',
        'mp2'   => 'audio/mpeg',
        'mp3'   => ['audio/mpeg', 'audio/mpg', 'audio/mpeg3', 'audio/mp3'],
        'mp4'   => 'video/mp4',
        'mpe'   => 'video/mpeg',
        'mpeg'  => 'video/mpeg',
        'mpg'   => 'video/mpeg',
        'mpga'  => 'audio/mpeg',
        'odc'   => 'application/vnd.oasis.opendocument.chart',
        'odp'   => 'application/vnd.oasis.opendocument.presentation',
        'odt'   => 'application/vnd.oasis.opendocument.text',
        'pdf'   => ['application/pdf', 'application/x-download'],
        'php'   => ['text/php', 'text/x-php', 'application/x-httpd-php', 'application/php', 'application/x-php', 'application/x-httpd-php-source'],
        'php3'  => ['text/php', 'text/x-php', 'application/x-httpd-php', 'application/php', 'application/x-php', 'application/x-httpd-php-source'],
        'phps'  => ['text/php', 'text/x-php', 'application/x-httpd-php', 'application/php', 'application/x-php', 'application/x-httpd-php-source'],
        'phtml' => ['text/php', 'text/x-php', 'application/x-httpd-php', 'application/php', 'application/x-php', 'application/x-httpd-php-source'],
        'png'   => 'image/png',
        'ppt'   => ['application/powerpoint', 'application/vnd.ms-powerpoint'],
        'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'potx'  => 'application/vnd.openxmlformats-officedocument.presentationml.template',
        'ps'    => 'application/postscript',
        'psd'   => 'application/x-photoshop',
        'qt'    => 'video/quicktime',
        'rss'   => 'application/rss+xml',
        'rtf'   => 'text/rtf',
        'rtx'   => 'text/richtext',
        'shtml' => 'text/html',
        'svg'   => 'image/svg+xml',
        'swf'   => 'application/x-shockwave-flash',
        'tar'   => 'application/x-tar',
        'text'  => 'text/plain',
        'txt'   => 'text/plain',
        'tgz'   => ['application/x-tar', 'application/x-gzip-compressed'],
        'tif'   => 'image/tiff',
        'tiff'  => 'image/tiff',
        'wav'   => 'audio/x-wav',
        'wbxml' => 'application/wbxml',
        'webm'  => 'video/webm',
        'webp'  => 'image/webp',
        'word'  => ['application/msword', 'application/octet-stream'],
        'xhtml' => 'application/xhtml+xml',
        'xht'   => 'application/xhtml+xml',
        'xml'   => 'text/xml',
        'xl'    => 'application/excel',
        'xls'   => ['application/excel', 'application/vnd.ms-excel', 'application/msexcel'],
        'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xltx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
        'xsl'   => 'text/xml',
        'yaml'  => ['application/yaml', 'text/yaml'],
        'yml'   => ['application/yaml', 'text/yaml'],
        'zip'   => ['application/x-zip', 'application/zip', 'application/x-zip-compressed'],
    ];


    /**
     * Guesses a MIME type by extension
     *
     * @param string $extension
     * @return string|null
     */
    public static function fromExtension(string $extension): ?string
    {
        $mime = static::$types[$extension] ?? null;
        return is_array($mime) === true ? array_shift($mime) : $mime;
    }


    /**
     * Returns the MIME type of a file
     *
     * @param string $file
     * @return string|false
     */
    public static function fromFileInfo(string $file)
    {
        if (function_exists('finfo_file') === true && file_exists($file) === true) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file);
            finfo_close($finfo);
            return $mime;
        }

        return false;
    }


    /**
     * Returns the MIME type of a file
     *
     * @param string $file
     * @return string|false
     */
    public static function fromMimeContentType(string $file)
    {
        if (function_exists('mime_content_type') === true && file_exists($file) === true) {
            return mime_content_type($file);
        }

        return false;
    }


    /**
     * Returns the MIME type of a file
     *
     * @param string $file
     * @param string $extension
     * @return string|false
     */
    public static function type(string $file, string $extension = null)
    {
        // use the standard finfo extension
        $mime = static::fromFileInfo($file);

        // use the mime_content_type function
        if ($mime === false) {
            $mime = static::fromMimeContentType($file);
        }

        // get the extension or extract it from the filename
        $extension ??= F::extension($file);

        // try to guess the mime type at least
        if ($mime === false) {
            $mime = static::fromExtension($extension);
        }

        return $mime;
    }
}
