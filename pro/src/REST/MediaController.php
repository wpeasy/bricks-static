<?php
/**
 * Media REST controller.
 *
 * @package WPEasy\BricksStaticPro
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStaticPro\REST;

use WP_REST_Response;
use WPEasy\BricksStaticPro\Media\MediaCollector;

defined('ABSPATH') || exit;

/**
 * Serves the media discovered in the last render, for the media replacer UI.
 */
final class MediaController {

    /**
     * REST namespace.
     */
    private const NS = 'bs/v1';

    /**
     * Register routes.
     */
    public static function register(): void {
        register_rest_route(self::NS, '/media', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'list_media'],
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
     * GET /media — every image/video the rendered site references.
     */
    public static function list_media(): WP_REST_Response {
        return new WP_REST_Response(['media' => MediaCollector::collect()]);
    }
}
