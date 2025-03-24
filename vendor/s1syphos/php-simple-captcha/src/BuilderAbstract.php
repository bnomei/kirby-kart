<?php

declare(strict_types=1);

namespace SimpleCaptcha;

use SimpleCaptcha\Helpers\Str;

/**
 * Class BuilderAbstract
 *
 * Base template for class 'Builder'
 */
abstract class BuilderAbstract
{
    /**
     * Properties
     */

    /**
     * Captcha phrase
     *
     * @var string
     */
    public string $phrase;


    /**
     * Characters used for building random phrases
     *
     * @var string
     */
    protected static string $charset = 'abcdefghijklmnpqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ123456789';


    /**
     * Abstract methods
     */

    /**
     * Builds captcha image
     *
     * @param int $width Captcha image width
     * @param int $height Captcha image height
     * @return self
     */
    abstract public function build(int $width, int $height): self;


    /**
     * Saves captcha image to file
     *
     * @param string $filename Output filepath
     * @param int $quality Captcha image quality
     * @return void
     */
    abstract public function save(string $filename, int $quality = 90): void;


    /**
     * Outputs captcha image directly
     *
     * @param int $quality Captcha image quality
     * @param string $type Captcha image output format
     * @return void
     */
    abstract public function output(int $quality = 90, string $type = 'jpg'): void;


    /**
     * Fetches captcha image contents
     *
     * @param int $quality Captcha image quality
     * @param string $type Captcha image output format
     * @return string
     */
    abstract public function fetch(int $quality = 90, string $type = 'jpg'): string;


    /**
     * Fetches captcha image as data URI
     *
     * @param int $quality Captcha image quality
     * @param string $type Captcha image output format
     * @return string
     */
    abstract public function inline(int $quality = 90, string $type = 'jpg'): string;


    /**
     * Helper methods
     */

    /**
     * Picks random character
     *
     * @param string|null $charset Characters to choose from
     * @return string
     */
    public static function randomCharacter(?string $charset = null): string
    {
        # Determine characters to use
        if (is_null($charset)) {
            $charset = self::$charset;
        }

        # Create charset array
        $characters = str_split($charset);

        # Return random character
        #
        # Note: This provides same performance as `array_rand`, which uses `mt_rand` under the hood
        # See https://www.php.net/manual/en/migration71.incompatible.php#migration71.incompatible.rand-srand-aliases
        #
        # For details on `rand` versus `mt_rand` performance,
        # see https://stackoverflow.com/a/7808258
        return $characters[mt_rand(0, count($characters) - 1)];
    }


    /**
     * Builds random phrase
     *
     * @param int $length Number of characters
     * @param string|null $charset Characters to choose from
     * @return string
     */
    public static function buildPhrase(int $length = 5, ?string $charset = null): string
    {
        # Build random string
        $phrase = '';

        for ($i = 0; $i < $length; $i++) {
            $phrase .= self::randomCharacter($charset);
        }

        return $phrase;
    }


    /**
     * Normalizes characters which look (almost) the same
     *
     * @param string $string
     * @return string
     */
    private static function normalize(string $string): string
    {
        return strtr(Str::lower($string), '01', 'ol');
    }


    /**
     * Checks whether captcha was solved correctly
     *
     * @param string $phrase Phrase to be tested
     * @param string|null $string Phrase to be tested against (optional)
     * @return bool
     */
    public function compare(string $phrase, ?string $string = null): bool
    {
        return self::normalize($phrase) == self::normalize($string ?? $this->phrase);
    }
}
