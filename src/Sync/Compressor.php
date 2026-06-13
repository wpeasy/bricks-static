<?php
/**
 * Gzip pre-compression of text output.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Sync;

defined('ABSPATH') || exit;

/**
 * Writes `.gz` siblings next to compressible files so the destination can serve
 * pre-compressed responses (see HtaccessBuilder in M3).
 */
final class Compressor {

    /**
     * Extensions worth pre-compressing.
     */
    private const TEXT_EXTENSIONS = ['html', 'htm', 'css', 'js', 'mjs', 'svg', 'json', 'xml', 'txt', 'map'];

    /**
     * Files smaller than this aren't worth gzipping — the ~20-byte gzip overhead
     * can make the sibling LARGER (e.g. a 76-byte robots.txt), and the per-request
     * round trip to swap in a .gz isn't worth it. Filter `bs_gzip_min_bytes`.
     */
    private const MIN_BYTES = 1024;

    /**
     * Whether gzip is available.
     */
    public static function available(): bool {
        return function_exists('gzencode');
    }

    /**
     * Minimum file size (bytes) before a .gz sibling is worth writing.
     */
    public static function min_bytes(): int {
        return (int) apply_filters('bs_gzip_min_bytes', self::MIN_BYTES);
    }

    /**
     * Extensions worth pre-compressing (for the server-side deploy helper).
     *
     * @return array<int,string>
     */
    public static function extensions(): array {
        return self::TEXT_EXTENSIONS;
    }

    /**
     * Whether a relative path looks like a compressible text file.
     *
     * @param string $relative_path Relative file path.
     */
    public static function is_compressible(string $relative_path): bool {
        $ext = strtolower(pathinfo($relative_path, PATHINFO_EXTENSION));

        return in_array($ext, self::TEXT_EXTENSIONS, true);
    }

    /**
     * Write a gzip sibling for a file (file.ext → file.ext.gz).
     *
     * @param string $absolute_path Absolute path to the source file.
     * @return bool True on success.
     */
    public static function write_sibling(string $absolute_path): bool {
        if (!self::available()) {
            return false;
        }

        $data = file_get_contents($absolute_path);
        if ($data === false) {
            return false;
        }

        // Too small to bother — drop any stale sibling so it can't be served.
        if (strlen($data) < self::min_bytes()) {
            if (is_file($absolute_path . '.gz')) {
                @unlink($absolute_path . '.gz');
            }
            return false;
        }

        $gz = gzencode($data, 9);
        if ($gz === false || strlen($gz) >= strlen($data)) {
            if (is_file($absolute_path . '.gz')) {
                @unlink($absolute_path . '.gz');
            }
            return false;
        }

        return file_put_contents($absolute_path . '.gz', $gz) !== false;
    }

    /**
     * Gzip a source file to a specific destination path.
     *
     * @param string $source      Absolute source file.
     * @param string $destination Absolute destination (.gz) path.
     * @return bool True on success.
     */
    public static function gzip_to(string $source, string $destination): bool {
        if (!self::available()) {
            return false;
        }

        $data = file_get_contents($source);
        if ($data === false || strlen($data) < self::min_bytes()) {
            return false;
        }

        $gz = gzencode($data, 9);
        if ($gz === false || strlen($gz) >= strlen($data)) {
            return false;
        }

        return file_put_contents($destination, $gz) !== false;
    }
}
