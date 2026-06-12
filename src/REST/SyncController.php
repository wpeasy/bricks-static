<?php
/**
 * Sync REST controller (start / tick / status / cancel).
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\REST;

use WP_REST_Request;
use WP_REST_Response;
use WPEasy\BricksStatic\Sync\Runner;

defined('ABSPATH') || exit;

/**
 * Drives the browser-driven batched run: start a job, then poll tick() until it
 * is no longer running. M2 supports the "check" (dry-run) type; "sync" (push)
 * lands in M3.
 */
final class SyncController {

    /**
     * REST namespace.
     */
    private const NS = 'bs/v1';

    /**
     * Register routes.
     */
    public static function register(): void {
        register_rest_route(self::NS, '/sync/start', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'start'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/sync/tick', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'tick'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/sync', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'status'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/sync/cancel', [
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
     * POST /sync/start — begin a run.
     *
     * @param WP_REST_Request $request Request.
     */
    public static function start(WP_REST_Request $request): WP_REST_Response {
        $type = (string) ($request->get_json_params()['type'] ?? 'check');

        // Only the dry run is available until M3 adds the upload phase.
        if ($type !== 'check') {
            return new WP_REST_Response(['error' => 'Only "check" is available in this version.'], 400);
        }

        try {
            return new WP_REST_Response(Runner::start($type));
        } catch (\Throwable $e) {
            return new WP_REST_Response(['phase' => 'error', 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * POST /sync/tick — process one batch.
     */
    public static function tick(): WP_REST_Response {
        try {
            return new WP_REST_Response(Runner::tick());
        } catch (\Throwable $e) {
            return new WP_REST_Response(['phase' => 'error', 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * GET /sync — current job snapshot.
     */
    public static function status(): WP_REST_Response {
        return new WP_REST_Response(Runner::status());
    }

    /**
     * POST /sync/cancel — request cancellation.
     */
    public static function cancel(): WP_REST_Response {
        Runner::cancel();

        return new WP_REST_Response(Runner::status());
    }
}
