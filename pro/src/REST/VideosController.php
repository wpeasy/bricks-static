<?php
/**
 * Videos (iframe embeds) REST controller.
 *
 * @package WPEasy\BricksStaticPro
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStaticPro\REST;

use WP_REST_Response;
use WPEasy\BricksStaticPro\Media\VideoCollector;

defined('ABSPATH') || exit;

/**
 * Serves the iframe embeds discovered in the last render, for the video replacer UI.
 */
final class VideosController {

    /**
     * REST namespace.
     */
    private const NS = 'bs/v1';

    /**
     * Register routes.
     */
    public static function register(): void {
        register_rest_route(self::NS, '/videos', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'list_videos'],
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
     * GET /videos — every iframe embed the rendered site references.
     */
    public static function list_videos(): WP_REST_Response {
        return new WP_REST_Response(['videos' => VideoCollector::collect()]);
    }
}
