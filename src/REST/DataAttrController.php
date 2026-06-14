<?php
/**
 * Data attributes REST controller.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\REST;

use WP_REST_Response;
use WPEasy\BricksStatic\Media\DataAttrCollector;

defined('ABSPATH') || exit;

/**
 * Serves the data-* attribute values discovered in the last render, for the data
 * attribute replacer UI.
 */
final class DataAttrController {

    /**
     * REST namespace.
     */
    private const NS = 'bs/v1';

    /**
     * Register routes.
     */
    public static function register(): void {
        register_rest_route(self::NS, '/data-attributes', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'list_attrs'],
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
     * GET /data-attributes — every data-* (attr, value) the rendered site uses.
     */
    public static function list_attrs(): WP_REST_Response {
        return new WP_REST_Response(['attributes' => DataAttrCollector::collect()]);
    }
}
