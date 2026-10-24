<?php
/**
 * Scheduled sync settings.
 *
 * @package WPEasy\ShipStatic
 * @since   2.3.0
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\REST;

use WP_REST_Request;
use WP_REST_Response;
use WPEasy\ShipStatic\Sync\Scheduler;

defined('ABSPATH') || exit;

/**
 * GET/POST /shipstatic/v1/schedule — admin only (SECURITY_PATTERNS.md §0).
 */
final class ScheduleController {

    private const NS = 'shipstatic/v1';

    /**
     * Register the route.
     */
    public static function register(): void {
        register_rest_route(self::NS, '/schedule', [
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'get'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'save'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);
    }

    public static function can_manage(): bool {
        return current_user_can('manage_options');
    }

    /**
     * GET /schedule.
     */
    public static function get(): WP_REST_Response {
        return new WP_REST_Response(self::payload());
    }

    /**
     * POST /schedule — {enabled?, period?, all?, destinations?}.
     *
     * @param WP_REST_Request $request Request.
     */
    public static function save(WP_REST_Request $request): WP_REST_Response {
        Scheduler::save((array) $request->get_json_params());

        return new WP_REST_Response(self::payload());
    }

    /**
     * @return array<string,mixed>
     */
    private static function payload(): array {
        return Scheduler::settings() + [
            'nextRun'      => Scheduler::next_run(),
            'last'         => Scheduler::last(),
            // With WP-Cron on (the default) runs need site traffic to fire on time.
            'cronDisabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
        ];
    }
}
