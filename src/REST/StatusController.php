<?php
/**
 * Status REST controller.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\REST;

use WP_REST_Response;
use WPEasy\BricksStatic\Sync\MethodResolver;

defined('ABSPATH') || exit;

/**
 * Reports the three dashboard indicators (connected, pushed, in sync) plus the
 * resolved sync method.
 */
final class StatusController {

    /**
     * REST namespace.
     */
    private const NS = 'bs/v1';

    /**
     * Register routes.
     */
    public static function register(): void {
        register_rest_route(self::NS, '/status', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get'],
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
     * GET /status — dashboard status snapshot.
     */
    public static function get(): WP_REST_Response {
        $state    = get_option('bs_connection_state', []);
        $manifest = get_option('bs_pushed_manifest', []);

        $connected   = is_array($state) && !empty($state['ok']);
        $has_pushed  = is_array($manifest) && !empty($manifest);

        return new WP_REST_Response([
            'connected' => $connected,
            'hasPushed' => $has_pushed,
            // Without a destination agent yet, "in sync" can only be asserted once
            // a push has happened and nothing has changed since (computed in M3).
            'inSync'    => false,
            'lastTest'  => is_array($state) ? [
                'ok'      => !empty($state['ok']),
                'time'    => isset($state['time']) ? (int) $state['time'] : 0,
                'message' => isset($state['message']) ? (string) $state['message'] : '',
            ] : null,
            'method'    => MethodResolver::resolve(),
        ]);
    }
}
