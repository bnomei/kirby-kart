<?php

declare(strict_types=1);

namespace SimpleCaptcha;

use SimpleCaptcha\Helpers\A;
use SimpleCaptcha\Helpers\F;
use SimpleCaptcha\Helpers\Dir;
use SimpleCaptcha\Helpers\Str;
use SimpleCaptcha\Helpers\Mime;

use thiagoalessio\TesseractOCR\TesseractOCR;

use GdImage;
use Exception;

/**
 * Class Builder
 *
 * Utilities for generating captcha images
 */
class Builder extends BuilderAbstract
{
    /**
     * Properties
     */

    /**
     * Captcha image
     *
     * @var GdImage
     */
    public GdImage $image;


    /**
     * Image width
     *
     * @var int
     */
    public int $width;


    /**
     * Image height
     *
     * @var int
     */
    public int $height;


    /**
     * Path to captcha font(s)
     *
     * @var array
     */
    public array $fonts;


    /**
     * Whether to distort the image
     *
     * @var bool
     */
    public bool $distort = true;


    /**
     * Whether to interpolate the image
     *
     * @var bool
     */
    public bool $interpolate = true;


    /**
     * Maximum number of lines behind the captcha phrase
     *
     * @var int|null
     */
    public ?int $maxLinesBehind = null;


    /**
     * Maximum number of lines in front of the captcha phrase
     *
     * @var int|null
     */
    public ?int $maxLinesFront = null;


    /**
     * Maximum character angle
     *
     * @var int
     */
    public int $maxAngle = 8;


    /**
     * Maximum character offset
     *
     * @var int
     */
    public int $maxOffset = 5;


    /**
     * Background color, either ..
     *
     * (1) .. RGB values (array)
     * (2) .. HEX value (string)
     * (3) .. 'transparent' (string)
     *
     * @var array|string
     */
    public array|string $bgColor = 'transparent';


    /**
     * Background color code
     *
     * @var int
     */
    private int $bgCode;


    /**
     * Line color, either ..
     *
     * (1) .. RGB values (array)
     * (2) .. HEX value (string)
     *
     * @var array|string
     */
    public array|string $lineColor;


    /**
     * Text color, either ..
     *
     * (1) .. RGB values (array)
     * (2) .. HEX value (string)
     *
     * @var array|string
     */
    public array|string $textColor;


    /**
     * Path to background image
     *
     * @var string|null
     */
    public ?string $bgImage = null;


    /**
     * Whether to apply (any) effects
     *
     * @var bool
     */
    public bool $applyEffects = true;


    /**
     * Whether to apply background noise (using random letters)
     *
     * @var bool
     */
    public bool $applyNoise = true;


    /**
     * Multiples of phrase length to be used for noise generation
     *
     * @var int
     */
    public int $noiseFactor = 2;


    /**
     * Whether to apply post effects
     *
     * @var bool
     */
    public bool $applyPostEffects = true;


    /**
     * Whether to enable scatter effect
     *
     * @var bool
     */
    public bool $applyScatterEffect = true;


    /**
     * Whether to use random font for each symbol
     *
     * @var bool
     */
    public bool $randomizeFonts = true;


    /**
     * Constructor
     *
     * @param string|null $phrase Captcha phrase
     * @return void
     */
    public function __construct(?string $phrase = null)
    {
        # Build random phrase if missing input or empty string
        $this->phrase = $phrase ?: $this->buildPhrase();

        # Fetch default font files
        $this->fonts = Dir::files(__DIR__ . '/../fonts', null, true);
    }


    /**
     * Methods
     */

    /**
     * Instantiates 'CaptchaBuilder' object
     *
     * @param string|null $phrase Captcha phrase
     * @return self
     */
    public static function create(?string $phrase = null): self
    {
        return new self($phrase);
    }


    /**
     * Determines (and validates) colors
     *
     * @param string|array $color Color values, either HEX (string) or RGB (array)
     * @return array
     * @throws \Exception
     */
    private function getColor(string|array $color): array
    {
        # If value represents RGB values ..
        if (is_array($color)) {
            # .. validate them
            if (count($color) != 3) {
                throw new Exception(sprintf('Invalid RGB colors: "%s"', A::join($color)));
            }

            return $color;
        }

        return Toolkit::hex2rgb($color);
    }


    /**
     * Draws lines over the image
     *
     * @param int|null $color Line color
     * @return void
     */
    private function drawLine(?int $color = null): void
    {
        # Determine direction at random, being either ..
        # (1) .. horizontal
        if (mt_rand(0, 1)) {
            $Xa = mt_rand(0, $this->width / 2);
            $Ya = mt_rand(0, $this->height);
            $Xb = mt_rand($this->width / 2, $this->width);
            $Yb = mt_rand(0, $this->height);

        # (2) .. vertical
        } else {
            $Xa = mt_rand(0, $this->width);
            $Ya = mt_rand(0, $this->height / 2);
            $Xb = mt_rand(0, $this->width);
            $Yb = mt_rand($this->height / 2, $this->height);
        }

        # Unless line color was provided ..
        if (is_null($color)) {
            # .. assign it
            # (1) Determine colors to be mixed
            $mix = $this->lineColor ?? [
                mt_rand(100, 255),  # red
                mt_rand(100, 255),  # green
                mt_rand(100, 255),  # blue
            ];

            # (2) Normalize RGB values
            $mix = $this->getColor($mix);

            # (3) Mix them up
            $color = imagecolorallocate($this->image, $mix[0], $mix[1], $mix[2]);
        }

        # Randomize thickness & draw line
        imagesetthickness($this->image, mt_rand(1, 3));
        imageline($this->image, $Xa, $Ya, $Xb, $Yb, $color);
    }


    /**
     * Applies background noise (using random letters)
     *
     * @return void
     */
    private function applyNoise(): void
    {
        for ($i = 0; $i < Str::length($this->phrase) * $this->noiseFactor; $i++) {
            # Determine random letter ..
            $character = static::randomCharacter();
            $font = $this->randomFont();

            # .. of random size & color, ..
            $fontSize = mt_rand(5, 10);
            $textColor = imagecolorallocate($this->image, mt_rand(0, 128), mt_rand(0, 128), mt_rand(0, 128));

            # .. random position ..
            $x = mt_rand(0, $this->width);
            $y = mt_rand(0, $this->height);

            # .. random angle ..
            $angle = mt_rand(-45, 45);

            # .. and apply it
            imagettftext($this->image, $fontSize, $angle, $x, $y, $textColor, $font, $character);
        }
    }


    /**
     * Picks random font file
     *
     * @return string Path to random font file
     * @throws \Exception Font file does not exist
     */
    private function randomFont(): string
    {
        # Pick random font file
        $font = $this->fonts[mt_rand(0, count($this->fonts) - 1)];

        # If it exists ..
        if (F::exists($font)) {
            # .. use it
            return $font;
        }

        # .. fail otherwise
        throw new Exception(sprintf('File does not exist: "%s"', $font));
    }


    /**
     * Writes captcha phrase on captcha image
     *
     * @return void
     */
    private function writePhrase(): void
    {
        # Determine number of characters
        $length = Str::length($this->phrase);

        # Choose random font
        $font = $this->randomFont();

        # Determine text size & start position
        $size = $this->round($this->width / $length) - mt_rand(0, 3) - 1;
        $box = imagettfbbox($size, 0, $font, $this->phrase);
        $textWidth = $box[2] - $box[0];
        $textHeight = $box[1] - $box[7];
        $x = $this->round(($this->width - $textWidth) / 2);
        $y = $this->round(($this->height - $textHeight) / 2) + $size;

        # Write individual letters ..
        for ($i = 0; $i < $length; $i++) {
            # (1) .. using random font (if enabled)
            if ($this->randomizeFonts) {
                $font = $this->randomFont();
            }

            # (2) .. using random text color (if enabled)
            # (a) Determine colors to be mixed
            $mix = $this->textColor ?? [
                mt_rand(0, 150),  # red
                mt_rand(0, 150),  # green
                mt_rand(0, 150),  # blue
            ];

            # (b) Normalize RGB values
            $mix = $this->getColor($mix);

            # (c) Mix them up
            $textCode = imagecolorallocate($this->image, $mix[0], $mix[1], $mix[2]);

            # Fetch current character & determine its width
            $char = Str::substr($this->phrase, $i, 1);
            $box = imagettfbbox($size, 0, $font, $char);
            $charWidth = $box[2] - $box[0];

            # Randomize angle & offset
            $angle = mt_rand(-$this->maxAngle, $this->maxAngle);
            $offset = mt_rand(-$this->maxOffset, $this->maxOffset);

            # Draw character
            imagettftext($this->image, $size, $angle, $x, $y + $offset, $textCode, $font, $char);

            # Move along
            $x += $charWidth;
        }
    }


    /**
     * Applies post effects
     *
     * @return void
     */
    private function applyPostEffects(): void
    {
        if (!function_exists('imagefilter')) {
            return;
        }

        # Scatter/Noise - Added in PHP 7.4
        $scattered = false;

        if (version_compare(PHP_VERSION, '7.4.0') >= 0) {
            if ($this->applyScatterEffect && mt_rand(0, 3) != 0) {
                $scattered = true;
                imagefilter($this->image, IMG_FILTER_SCATTER, 0, 2, [$this->bgCode]);
            }
        }

        # Negate ?
        if (mt_rand(0, 1) == 0) {
            imagefilter($this->image, IMG_FILTER_NEGATE);
        }

        # Edge ?
        if (!$scattered && mt_rand(0, 10) == 0) {
            imagefilter($this->image, IMG_FILTER_EDGEDETECT);
        }

        # Contrast
        imagefilter($this->image, IMG_FILTER_CONTRAST, mt_rand(-50, 10));

        # Colorize
        if (!$scattered && mt_rand(0, 5) == 0) {
            imagefilter($this->image, IMG_FILTER_COLORIZE, mt_rand(-80, 50), mt_rand(-80, 50), mt_rand(-80, 50));
        }
    }


    /**
     * Interpolates image
     *
     * @param int $x
     * @param int $y
     * @param int $nw
     * @param int $ne
     * @param int $sw
     * @param int $se
     * @return int
     */
    private function interpolate(int $x, int $y, int $nw, int $ne, int $sw, int $se): int
    {
        list($r0, $g0, $b0) = Toolkit::int2rgb($nw);
        list($r1, $g1, $b1) = Toolkit::int2rgb($ne);
        list($r2, $g2, $b2) = Toolkit::int2rgb($sw);
        list($r3, $g3, $b3) = Toolkit::int2rgb($se);

        $cx = 1.0 - $x;
        $cy = 1.0 - $y;

        $m0 = $cx * $r0 + $x * $r1;
        $m1 = $cx * $r2 + $x * $r3;
        $r  = (int) ($cy * $m0 + $y * $m1);

        $m0 = $cx * $g0 + $x * $g1;
        $m1 = $cx * $g2 + $x * $g3;
        $g  = (int) ($cy * $m0 + $y * $m1);

        $m0 = $cx * $b0 + $x * $b1;
        $m1 = $cx * $b2 + $x * $b3;
        $b  = (int) ($cy * $m0 + $y * $m1);

        return ($r << 16) | ($g << 8) | $b;
    }


    /**
     * Makes image background transparent
     *
     * @param GdImage $image
     * @return void
     */
    private function addTransparency(GdImage $image): void
    {
        imagealphablending($image, false);
        $transparency = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparency);
        imagesavealpha($image, true);
    }


    /**
     * Fetches color of pixel at given coordinates
     *
     * @param float|int $x Horizontal position (or an estimation thereof)
     * @param float|int $y Vertical position (or an estimation thereof)
     * @return int
     */
    private function pixel2int(float|int $x, float|int $y): int
    {
        if ($x < 0 || $x >= $this->width || $y < 0 || $y >= $this->height) {
            return $this->bgCode;
        }

        return imagecolorat($this->image, $this->round($x), $this->round($y));
    }


    /**
     * Distorts image
     *
     * @return void
     */
    private function distort(): void
    {
        $image = imagecreatetruecolor($this->width, $this->height);

        # If background transparency is enabled ..
        if ($this->bgColor == 'transparent') {
            # .. apply it
            $this->addTransparency($image);

            # .. initialize background color code
            $this->bgCode = mt_rand(0, 100);
        }

        $X = mt_rand(0, $this->width);
        $Y = mt_rand(0, $this->height);
        $phase = mt_rand(0, 10);
        $scale = 1.1 + mt_rand(0, 10000) / 30000;

        for ($x = 0; $x < $this->width; $x++) {
            for ($y = 0; $y < $this->height; $y++) {
                $Vx = $x - $X;
                $Vy = $y - $Y;
                $Vn = sqrt($Vx * $Vx + $Vy * $Vy);

                if ($Vn != 0) {
                    $Vn2 = $Vn + 4 * sin($Vn / 30);
                    $nX  = $X + ($Vx * $Vn2 / $Vn);
                    $nY  = $Y + ($Vy * $Vn2 / $Vn);
                } else {
                    $nX = $X;
                    $nY = $Y;
                }

                $nY = $nY + $scale * sin($phase + $nX * 0.2);

                if ($this->interpolate && $this->bgColor != 'transparent') {
                    $p = $this->interpolate(
                        $this->round($nX - floor($nX)),
                        $this->round($nY - floor($nY)),
                        $this->pixel2int(floor($nX), floor($nY)),
                        $this->pixel2int(ceil($nX), floor($nY)),
                        $this->pixel2int(floor($nX), ceil($nY)),
                        $this->pixel2int(ceil($nX), ceil($nY))
                    );
                } else {
                    $p = $this->pixel2int($this->round($nX), $this->round($nY));
                }

                if ($p == 0) {
                    $p = $this->bgCode;
                }

                imagesetpixel($image, $x, $y, $p);
            }
        }

        $this->image = $image;
    }


    /**
     * Builds captcha image
     *
     * @param int $width Captcha image width
     * @param int $height Captcha image height
     * @return self
     */
    public function build(int $width = 150, int $height = 40): self
    {
        # Apply image dimensions
        $this->width = $width;
        $this->height = $height;

        # If background image available ..
        if (!is_null($this->bgImage)) {
            # (1) .. create image from it
            $this->image = $this->img2gd($this->bgImage);

            # (2) .. extract background color from it
            $this->bgCode = imagecolorat($this->image, 0, 0);

        # .. otherwise ..
        } else {
            # .. start from scratch
            $this->image = imagecreatetruecolor($this->width, $this->height);

            # If background transparency is enabled ..
            if ($this->bgColor == 'transparent') {
                # .. apply it
                $this->addTransparency($this->image);
            }

            # .. otherwise ..
            else {
                # .. assign background color
                # (1) Determine colors to be mixed
                $mix = $this->bgColor ?? [
                    mt_rand(200, 255),  # red
                    mt_rand(200, 255),  # green
                    mt_rand(200, 255),  # blue
                ];

                # (2) Normalize RGB values
                $mix = $this->getColor($mix);

                # (3) Mix them up
                $this->bgCode = imagecolorallocate($this->image, $mix[0], $mix[1], $mix[2]);

                # Fill image
                imagefill($this->image, 0, 0, $this->bgCode);
            }
        }

        # Calculate surface size
        $surface = $this->width * $this->height;

        # Apply effects
        if ($this->applyEffects) {
            $effects = $this->random_float($surface / 3000, $surface / 2000);

            # Set the maximum number of lines to draw in front of the text
            if (is_int($this->maxLinesBehind) && $this->maxLinesBehind > 0) {
                $effects = min($this->maxLinesBehind, $effects);
            }

            if ($this->maxLinesBehind !== 0) {
                for ($e = 0; $e < $effects; $e++) {
                    $this->drawLine();
                }
            }

            # Apply background noise
            if ($this->applyNoise) {
                $this->applyNoise();
            }
        }

        # Write captcha phrase & returns its color code
        $this->writePhrase();

        # Apply effects
        if ($this->applyEffects) {
            $effects = $this->random_float($surface / 3000, $surface / 2000);

            # Set the maximum number of lines to draw in front of the text
            if (is_int($this->maxLinesFront) && $this->maxLinesFront > 0) {
                $effects = min($this->maxLinesFront, $effects);
            }

            if ($this->maxLinesFront !== 0) {
                for ($e = 0; $e < $effects; $e++) {
                    $this->drawLine();
                }
            }

            # Distort the image
            if ($this->distort) {
                $this->distort();
            }
        }

        # Add post effects
        if ($this->applyEffects && $this->applyPostEffects) {
            $this->applyPostEffects();
        }

        return $this;
    }


    /**
     * Creates image file suitable for use with OCR software
     *
     * See https://priteshgupta.com/2011/09/advanced-image-functions-using-php
     * See https://github.com/raoulduke/phpocrad
     *
     * @param string $output Output file
     * @param int $amount
     * @param int $threshold
     * @return void
     */
    private function img2ocr(string $output, int $amount = 80, int $threshold = 3): void
    {
        $image = $this->image;

        $canvas = imagecreatetruecolor($this->width, $this->height);
        $blurred = imagecreatetruecolor($this->width, $this->height);

        # Apply gaussian blur matrix
        $matrix = [
            [1, 2, 1],
            [2, 4, 2],
            [1, 2, 1],
        ];

        imagecopy($blurred, $image, 0, 0, 0, 0, $this->width, $this->height);
        imageconvolution($blurred, $matrix, 16, 0);

        if ($threshold > 0) {
            # Calculate the difference between the blurred pixels and the original
            # and set the pixels
            for ($x = 0; $x < $this->width - 1; $x++) {  # each row
                for ($y = 0; $y < $this->height; $y++) { # each pixel
                    $rgbOrig = imagecolorat($image, $x, $y);

                    $rOrig = (($rgbOrig >> 16) & 0xFF);
                    $gOrig = (($rgbOrig >> 8) & 0xFF);
                    $bOrig = ($rgbOrig & 0xFF);

                    $rgbBlur = imagecolorat($blurred, $x, $y);

                    $rBlur = (($rgbBlur >> 16) & 0xFF);
                    $gBlur = (($rgbBlur >> 8) & 0xFF);
                    $bBlur = ($rgbBlur & 0xFF);

                    # When the masked pixels differ less from the original
                    # than the threshold specifies, they are set to their original value.
                    $rNew = (abs($rOrig - $rBlur) >= $threshold)
                        ? max(0, min(255, ($amount * ($rOrig - $rBlur)) + $rOrig))
                        : $rOrig;
                    $gNew = (abs($gOrig - $gBlur) >= $threshold)
                        ? max(0, min(255, ($amount * ($gOrig - $gBlur)) + $gOrig))
                        : $gOrig;
                    $bNew = (abs($bOrig - $bBlur) >= $threshold)
                        ? max(0, min(255, ($amount * ($bOrig - $bBlur)) + $bOrig))
                        : $bOrig;

                    if (($rOrig != $rNew) || ($gOrig != $gNew) || ($bOrig != $bNew)) {
                        $pixCol = ImageColorAllocate($image, $rNew, $gNew, $bNew);
                        imagesetpixel($image, $x, $y, $pixCol);
                    }
                }
            }
        } else {
            for ($x = 0; $x < $this->width; $x++) { # each row
                for ($y = 0; $y < $this->height; $y++) { # each pixel
                    $rgbOrig = imagecolorat($image, $x, $y);

                    $rOrig = (($rgbOrig >> 16) & 0xFF);
                    $gOrig = (($rgbOrig >> 8) & 0xFF);
                    $bOrig = ($rgbOrig & 0xFF);

                    $rgbBlur = imagecolorat($blurred, $x, $y);

                    $rBlur = (($rgbBlur >> 16) & 0xFF);
                    $gBlur = (($rgbBlur >> 8) & 0xFF);
                    $bBlur = ($rgbBlur & 0xFF);

                    $rNew = ($amount * ($rOrig - $rBlur)) + $rOrig;
                    if ($rNew > 255) {
                        $rNew = 255;
                    } elseif ($rNew < 0) {
                        $rNew = 0;
                    }
                    $gNew = ($amount * ($gOrig - $gBlur)) + $gOrig;
                    if ($gNew > 255) {
                        $gNew = 255;
                    } elseif ($gNew < 0) {
                        $gNew = 0;
                    }
                    $bNew = ($amount * ($bOrig - $bBlur)) + $bOrig;
                    if ($bNew > 255) {
                        $bNew = 255;
                    } elseif ($bNew < 0) {
                        $bNew = 0;
                    }
                    $rgbNew = ($rNew << 16) + ($gNew << 8) + $bNew;

                    imagesetpixel($image, $x, $y, $rgbNew);
                }
            }
        }

        # Remove temporary image data
        imagedestroy($canvas);
        imagedestroy($blurred);

        # Create PGM file (grayscale)
        $pgm = 'P5 ' . $this->width . ' ' . $this->height . ' 255' . "\n";

        for ($y = 0; $y < $this->height; $y++) {
            for ($x = 0; $x < $this->width; $x++) {
                # Compose color
                $colors = imagecolorsforindex($image, imagecolorat($image, $x, $y));

                # Extract its RGB values & make them gray-ish
                $red = $this->round(0.3 * $colors['red']);
                $green = $this->round(0.59 * $colors['green']);
                $blue = $this->round(0.11 * $colors['blue']);

                # Create single-byte string from them
                $pgm .= chr($red + $green + $blue);
            }
        }

        F::write($output, $pgm);
    }


    /**
     * Checks whether captcha image may be solved through OCR
     *
     * @param string $tmpDir Temporary directory
     * @return bool
     * @throws \Exception No OCR tool installed
     */
    public function isOCRReadable(string $tmpDir = '.tmp'): bool
    {
        $commands = [
            'ocrad' => 'ocrad --scale=2 --charset=ascii %s',
            'tesseract' => 'tesseract %s stdout -l eng --dpi 2200',
        ];

        $modes = [];

        foreach (array_keys($commands) as $mode) {
            if (empty(exec('command -v ' . $mode))) {
                continue;
            }

            $modes[] = $mode;
        }

        if (empty($modes)) {
            throw new Exception('OCR detection requires either "ocrad" or "tesseract-ocr" to be installed.');
        }

        # Create temporary directory (if necessary)
        Dir::make($tmpDir);

        # Join filepath & generate unique filename
        $pgmFile = sprintf('%s/%s.pgm', $tmpDir, uniqid('captcha'));

        # Create captcha image & convert to grayscale
        $this->img2ocr($pgmFile);

        # Create data array for possible matches
        $outputs = [];

        # Iterate over available modes ..
        foreach ($modes as $mode) {
            # .. using (suggested) external library (if available), otherwise ..
            if ($mode == 'tesseract' && class_exists(TesseractOCR::class)) {
                # Execute  `tesseract-ocr-for-php` & store its output
                $tesseract = new TesseractOCR($pgmFile);
                $outputs[] = $tesseract->allowlist(range(0, 9), range('a', 'z'), range('A', 'Z'))->dpi(2200)->run();
            }

            # .. falling back to shell commands
            else {
                # Execute OCR library from CLI
                $outputs[] = shell_exec(sprintf($commands[$mode], $pgmFile));
            }
        }

        # Delete temporary files & directory
        Dir::remove($tmpDir);

        # Iterate over possible matches
        foreach (array_filter($outputs) as $output) {
            # .. clean & validate them
            if ($this->compare(preg_replace('/[^a-z0-9]/i', '', $output))) {
                return true;
            }
        }

        return false;
    }


    /**
     * Builds captcha image until it is (supposedly) unreadable by OCR software
     *
     * @param int $width Captcha image width
     * @param int $height Captcha image height
     * @return self
     */
    public function buildAgainstOCR(int $width = 150, int $height = 40): self
    {
        do {
            $this->build($width, $height);
        } while ($this->isOCRReadable());

        return $this;
    }


    /**
     * Creates GD image object from file
     *
     * @param string $image Path to image file
     * @return GdImage
     * @throws \Exception Image file does not exist OR its MIME type is unsupported
     */
    protected function img2gd(string $file): GdImage
    {
        # If file does not exist ..
        if (!F::exists($file)) {
            # .. fail early
            throw new Exception(sprintf('File does not exist: "%s"', F::filename($file)));
        }

        $methods = [
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/gif' => 'imagecreatefromgif',
        ];

        $mime = Mime::type($file);

        if (in_array($mime, array_keys($methods))) {
            return $methods[$mime]($file);
        }

        throw new Exception(sprintf('MIME type "%s" not supported!', $mime));
    }


    /**
     * Creates image content from GD image object
     *
     * @param int $quality Captcha image quality
     * @param string $filename Output filepath
     * @param string $type Captcha image output format
     * @return void
     * @throws \Exception File type is unsupported
     */
    protected function gd2img(int $quality = 90, ?string $filename = null, string $type = 'jpg'): void
    {
        # Convert filetype to lowercase
        $type = Str::lower($type);

        # If filename is given ..
        if (!is_null($filename)) {
            # .. determine filetype from it
            $type = F::extension($filename);
        }

        if ($type == 'gif') {
            imagegif($this->image, $filename);
        } elseif ($type == 'jpg') {
            imagejpeg($this->image, $filename, $quality);
        } elseif ($type == 'png') {
            # Normalize quality
            if ($quality > 9) {
                $quality = -1;
            }

            imagepng($this->image, $filename, $quality);
        }

        # .. otherwise ..
        else {
            # .. abort execution
            throw new Exception(sprintf('File type "%s" not supported!', $type));
        }
    }


    /**
     * Saves captcha image to file
     *
     * @param string $filename Output filepath
     * @param int $quality Captcha image quality
     * @return void
     */
    public function save(string $filename, int $quality = 90): void
    {
        $this->gd2img($quality, $filename);
    }


    /**
     * Outputs captcha image directly
     *
     * @param int $quality Captcha image quality
     * @param string $type Captcha image filetype
     * @return void
     */
    public function output(int $quality = 90, string $type = 'jpg'): void
    {
        $this->gd2img($quality, null, $type);
    }


    /**
     * Fetches captcha image contents
     *
     * @param int $quality Captcha image quality
     * @param string $type Captcha image filetype
     * @return string
     */
    public function fetch(int $quality = 90, string $type = 'jpg'): string
    {
        # Enable output buffering
        ob_start();
        $this->output($quality, $type);

        return ob_get_clean();
    }


    /**
     * Fetches captcha image as data URI
     *
     * @param int $quality Captcha image quality
     * @param string $type Captcha image filetype
     * @return string
     */
    public function inline(int $quality = 90, string $type = 'jpg'): string
    {
        return sprintf('data:%s;base64,%s', Mime::fromExtension($type), base64_encode($this->fetch($quality, $type)));
    }


    /**
     * Helpers
     */

    /**
     * Rounds float to integer
     *
     * @param float $number
     * @return int
     */
    private function round(float $number): int
    {
        return (int) round($number);
    }


    /**
     * Creates random float between two digits
     *
     * @param float|int $min
     * @param float|int $max
     * @return float
     */
    private function random_float(float|int $min, float|int $max): float
    {
        # See https://www.php.net/manual/en/function.mt-rand.php#75793
        return ($min + lcg_value() * (abs($max - $min)));
    }
}
