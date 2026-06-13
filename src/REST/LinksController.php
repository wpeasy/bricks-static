<?php
/**
 * Links REST controller.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\REST;

use WP_REST_Response;
use WPEasy\BricksStatic\Media\LinkCollector;

defined('ABSPATH') || exit;

/**
 * Serves the link targets discovered in the last render, for the link replacer UI.
 */
final class LinksController {

    /**
     * REST namespace.
     */
    private const NS = 'bs/v1';

    /**
     * Register routes.
     */
    public static function register(): void {
        register_rest_route(self::NS, '/links', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'list_links'],
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
     * GET /links — every link target the rendered site references.
     */
    public static function list_links(): WP_REST_Response {
        return new WP_REST_Response(['links' => LinkCollector::collect()]);
    }
}
