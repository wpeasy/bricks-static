<?php
/**
 * Generates the Connector receiver script for a destination.
 *
 * @package WPEasy\BricksStatic
 * @since   2.1.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Deploy;

use WPEasy\BricksStatic\Sync\Compressor;

defined('ABSPATH') || exit;

/**
 * Renders `templates/connector.php.txt` for one pairing. The template is a real,
 * lintable PHP file (unlike the heredoc-built one-shot `UnzipScript`) because at
 * this size escaped-`$` heredoc source is unreviewable; only five placeholder
 * tokens are substituted, each with a value validated to be safe inside a PHP
 * string/expression.
 *
 * Security model (see SECURITY_PATTERNS.md §3b): the file embeds the sha256 of
 * the pairing secret — never the secret — so a host that serves it as text
 * leaks nothing reusable. Everything else about what the receiver will and
 * won't do is in the template itself.
 */
final class ConnectorScript {

    /**
     * Protocol version. Bump the number when a request/response shape changes
     * incompatibly — or when the receiver's security checks change, so every
     * deployed copy is replaced — the receiver rejects a mismatched client with
     * 409 so the dashboard can say "re-download the connector" precisely.
     *
     * 2: .htaccess allow-list at every depth; junk requests can't hold a lockout.
     */
    public const VERSION = 2;

    /**
     * Basename of the receiver for a connector id.
     *
     * @param string $connector_id 16-hex connector id.
     */
    public static function filename(string $connector_id): string {
        return 'bs-connector-' . $connector_id . '.php';
    }

    /**
     * Render the receiver source.
     *
     * @param string $connector_id 16-hex connector id (also the filename suffix).
     * @param string $secret       Raw pairing secret — only its hash is embedded.
     * @throws \InvalidArgumentException On a malformed id/secret.
     */
    public static function generate(string $connector_id, string $secret): string {
        if (!preg_match('/^[0-9a-f]{16}$/', $connector_id)) {
            throw new \InvalidArgumentException('Malformed connector id.');
        }
        if (!preg_match('/^[0-9a-f]{64}$/', $secret)) {
            throw new \InvalidArgumentException('Malformed connector secret.');
        }

        $template = (string) file_get_contents(__DIR__ . '/templates/connector.php.txt');

        return strtr($template, [
            '__BS_ID__'          => $connector_id,
            '__BS_SECRET_HASH__' => hash('sha256', $secret),
            '__BS_VERSION__'     => (string) self::VERSION,
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- renders a PHP array literal for the receiver, not debug output.
            '__BS_GZIP__'        => var_export(array_values(array_map('strval', Compressor::extensions())), true),
            '__BS_GZMIN__'       => (string) Compressor::min_bytes(),
        ]);
    }
}
