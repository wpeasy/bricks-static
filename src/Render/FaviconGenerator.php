<?php
/**
 * Generates a plain default favicon when the site has none.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Render;

defined('ABSPATH') || exit;

/**
 * Builds a simple .ico (a solid tile coloured from the site name, with the site's
 * initial) so the static export always ships a /favicon.ico and browsers don't
 * 404 on it. Used only as a last resort — a real favicon.ico or the WordPress
 * Site Icon takes precedence (see Runner::emit_favicon()).
 *
 * Output is deterministic for a given site name + GD build, so the favicon's hash
 * is stable across renders (it won't churn the manifest).
 */
final class FaviconGenerator {

    /**
     * Icon edge length in pixels.
     */
    private const SIZE = 32;

    /**
     * Generate the default favicon as binary .ico bytes ('' if it can't be built).
     */
    public static function ico(): string {
        $png = self::png();
        if ($png === '') {
            return '';
        }

        return self::wrap_ico($png, self::SIZE);
    }

    /**
     * Render the tile to PNG bytes via GD ('' if GD is unavailable).
     */
    private static function png(): string {
        if (!function_exists('imagecreatetruecolor')) {
            return '';
        }

        $size = self::SIZE;
        $name = (string) (get_bloginfo('name') ?: wp_parse_url(home_url(), PHP_URL_HOST));
        [$r, $g, $b] = self::brand_rgb($name);

        $im = imagecreatetruecolor($size, $size);
        $bg = imagecolorallocate($im, $r, $g, $b);
        imagefilledrectangle($im, 0, 0, $size, $size, $bg);

        // The site's first alphanumeric character, centred, in white.
        $clean  = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';
        $letter = $clean !== '' ? strtoupper($clean[0]) : 'S';
        $fg     = imagecolorallocate($im, 255, 255, 255);
        $font   = 5; // GD built-in: ~9x15px.
        $x      = (int) round(($size - imagefontwidth($font)) / 2);
        $y      = (int) round(($size - imagefontheight($font)) / 2);
        imagestring($im, $font, $x, $y, $letter, $fg);

        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return $png;
    }

    /**
     * A deterministic, reasonably-saturated RGB colour derived from a string.
     *
     * @param string $seed Seed (the site name).
     * @return array{0:int,1:int,2:int}
     */
    private static function brand_rgb(string $seed): array {
        $hash = md5($seed !== '' ? $seed : 'bricks-static');

        // Keep each channel in 40–215 so the white glyph stays legible.
        $chan = static fn(int $offset): int => 40 + (hexdec(substr($hash, $offset, 2)) % 176);

        return [$chan(0), $chan(2), $chan(4)];
    }

    /**
     * Wrap PNG bytes in a single-image ICO container (PNG-in-ICO, supported by all
     * current browsers).
     *
     * @param string $png  PNG image bytes.
     * @param int    $size Edge length in pixels (1–256).
     */
    private static function wrap_ico(string $png, int $size): string {
        $dim = $size >= 256 ? 0 : $size; // 0 in the ICO header means 256px.

        // ICONDIR: reserved=0, type=1 (icon), count=1.
        $header = pack('vvv', 0, 1, 1);

        // ICONDIRENTRY: w, h, colours, reserved, planes, bpp, byte-size, offset.
        $entry = pack(
            'CCCCvvVV',
            $dim,
            $dim,
            0,
            0,
            1,
            32,
            strlen($png),
            6 + 16 // header (6) + this entry (16)
        );

        return $header . $entry . $png;
    }
}
