<?php
/**
 * Export ZIP download endpoint.
 *
 * @package WPEasy\BricksStatic
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Export;

use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

/**
 * Streams a finished export zip and exits — the one deliberate departure from
 * "every REST controller returns JSON" in this codebase (see
 * SECURITY_PATTERNS.md §0). Stays a normal `bs/v1` REST route rather than a
 * new `admin_post_*`/`wp_ajax_*` handler, so that invariant stays accurate:
 * auth is the same `manage_options` + `wp_rest` nonce every other route uses
 * (WP core's REST cookie auth accepts `?_wpnonce=` as a query-param fallback
 * to the `X-WP-Nonce` header, so a plain `<a href>` download authenticates
 * correctly with no new nonce type introduced).
 *
 * The `token` query param is opaque and single-use (a transient set by {@see
 * ExportRunner}'s `saving` phase) — the real file path is never taken from
 * the request, which is what actually rules out path traversal here, not the
 * nonce.
 */
final class ExportDownload {

    /**
     * REST namespace.
     */
    private const NS = 'bs/v1';

    /**
     * Register the download route.
     */
    public static function register(): void {
        register_rest_route(self::NS, '/export/download', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'handle'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);
    }

    /**
     * Capability gate.
     */
    public static function can_manage(): bool {
        return current_user_can('manage_options');
    }

    /**
     * GET /export/download?token=… — stream the zip and exit, or return a
     * JSON error response if the token is missing/expired.
     *
     * @param WP_REST_Request $request Request.
     */
    public static function handle(WP_REST_Request $request) {
        $token = sanitize_text_field((string) $request->get_param('token'));
        if ($token === '') {
            return new WP_REST_Response(['message' => __('Missing download token.', 'bricks-static')], 400);
        }

        $record = get_transient('bs_export_dl_' . $token);
        if (!is_array($record) || empty($record['path']) || !is_file((string) $record['path'])) {
            return new WP_REST_Response(['message' => __('This export is no longer available — please export again.', 'bricks-static')], 404);
        }

        // Single-use: gone whether the download succeeds or the browser
        // aborts partway through.
        delete_transient('bs_export_dl_' . $token);

        $path     = (string) $record['path'];
        $filename = (string) ($record['filename'] ?? basename($path));

        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }
}
