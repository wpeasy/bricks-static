<?php
/**
 * Export ZIP REST controller (start / tick / status / cancel).
 *
 * @package WPEasy\BricksStatic
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\REST;

use WP_REST_Request;
use WP_REST_Response;
use WPEasy\BricksStatic\Export\ExportRunner;

defined('ABSPATH') || exit;

/**
 * Drives the browser-driven Export ZIP job: start, then poll tick() until it
 * is no longer running. Mirrors {@see SyncController}'s conventions at a
 * much smaller scale (no CLI driver/claim — export never leaves this process).
 */
final class ExportController {

    /**
     * REST namespace.
     */
    private const NS = 'bs/v1';

    /**
     * Register routes.
     */
    public static function register(): void {
        register_rest_route(self::NS, '/export/start', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'start'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/export/tick', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'tick'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/export', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'status'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/export/cancel', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'cancel'],
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
     * POST /export/start — begin an export for one destination.
     *
     * @param WP_REST_Request $request Request.
     */
    public static function start(WP_REST_Request $request): WP_REST_Response {
        $params  = (array) $request->get_json_params();
        $dest_id = (string) ($params['dest'] ?? '');

        try {
            return new WP_REST_Response(ExportRunner::start($dest_id));
        } catch (\Throwable $e) {
            return new WP_REST_Response(['phase' => 'error', 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * POST /export/tick — process one batch.
     */
    public static function tick(): WP_REST_Response {
        try {
            return new WP_REST_Response(ExportRunner::tick());
        } catch (\Throwable $e) {
            return new WP_REST_Response(['phase' => 'error', 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * GET /export — current job snapshot.
     */
    public static function status(): WP_REST_Response {
        return new WP_REST_Response(ExportRunner::status());
    }

    /**
     * POST /export/cancel — request cancellation.
     */
    public static function cancel(): WP_REST_Response {
        ExportRunner::cancel();

        return new WP_REST_Response(ExportRunner::status());
    }
}
