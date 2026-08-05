<?php
/**
 * Videos (iframe embeds) REST controller.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\REST;

use WP_REST_Request;
use WP_REST_Response;
use WPEasy\BricksStatic\Media\VideoCollector;

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
     * GET /videos — the exported pages (for the selector) and, when a `page` is
     * given, the videos on that page. Video replacement is per page, so no videos
     * are returned until a page is selected.
     *
     * @param WP_REST_Request $request Request.
     */
    public static function list_videos(WP_REST_Request $request): WP_REST_Response {
        $page = (string) $request->get_param('page');

        return new WP_REST_Response([
            'pages'  => VideoCollector::pages(),
            'page'   => $page,
            'videos' => $page !== '' ? VideoCollector::collect($page) : [],
        ]);
    }
}
