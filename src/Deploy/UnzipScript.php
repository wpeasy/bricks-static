<?php
/**
 * Generates the one-shot server-side deploy helper.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Deploy;

defined('ABSPATH') || exit;

/**
 * Builds the throwaway PHP script that the plugin uploads to a destination to
 * extract a deploy package locally (turning ~hundreds of slow per-file FTP
 * transfers into one zip upload). It is deliberately hardened:
 *
 *  - Runs only for a caller presenting the exact random token it was built with
 *    (the token is also in the filename, so it isn't even guessable in the URL).
 *  - Refuses to run after its short expiry.
 *  - Guards every zip entry and delete path against escaping the docroot
 *    (zip-slip / path traversal).
 *  - Deletes the package AND itself after an authorised run; the plugin also
 *    removes both over FTP as a backstop.
 *
 * The persistent Connector receiver (`templates/connector.php.txt`) carries the
 * same `$safe()` path rules and gzip-sibling logic — keep the two in sync.
 */
final class UnzipScript {

    /**
     * Render the deploy helper PHP source.
     *
     * @param string             $token        Random authorisation token (also used in the filename).
     * @param string             $zip_name     Basename of the uploaded package (same directory).
     * @param int                $expires      Unix timestamp after which the script refuses to run.
     * @param array<int,string>  $compressible Extensions to gzip server-side (no '.'), matching Compressor.
     * @param int                $min_bytes    Don't gzip files smaller than this (matches Compressor).
     */
    public static function generate(string $token, string $zip_name, int $expires, array $compressible = [], int $min_bytes = 0): string {
        // A lintable template with placeholders, not a heredoc (same pattern as
        // ConnectorScript). var_export() renders each value as a PHP literal.
        $template = (string) file_get_contents(__DIR__ . '/templates/unzip.php.txt');

        // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- generates PHP literals for the helper script, not debug output.
        return strtr($template, [
            '__BS_TOKEN__'   => var_export($token, true),
            '__BS_ZIPNAME__' => var_export($zip_name, true),
            '__BS_EXPIRES__' => var_export($expires, true),
            '__BS_GZIP__'    => var_export(array_values(array_map('strval', $compressible)), true),
            '__BS_GZMIN__'   => var_export($min_bytes, true),
        ]);
        // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_var_export
    }
}
