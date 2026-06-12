<?php
/**
 * Plugin bootstrap.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic;

use WPEasy\BricksStatic\Admin\Menu;
use WPEasy\BricksStatic\Render\PageRenderer;
use WPEasy\BricksStatic\Settings\Destinations;
use WPEasy\BricksStatic\REST\ConnectionController;
use WPEasy\BricksStatic\REST\DestinationsController;
use WPEasy\BricksStatic\REST\StatusController;
use WPEasy\BricksStatic\REST\SyncController;

defined('ABSPATH') || exit;

/**
 * Wires up the plugin's subsystems.
 */
final class Plugin {

    /**
     * Initialise the plugin.
     */
    public static function init(): void {
        // Migrate the legacy single destination into the destinations list once.
        Destinations::ensure_migrated();

        if (is_admin()) {
            Menu::init();
        }

        add_action('rest_api_init', [self::class, 'register_rest_routes']);

        // On our own loopback render requests only, tidy the captured output so
        // the static copy doesn't ship broken references.
        if (PageRenderer::is_render_request()) {
            add_action('init', [self::class, 'prepare_render_output']);
        }

        if (defined('WP_CLI') && \WP_CLI) {
            \WP_CLI::add_command('bricks-static', new \WPEasy\BricksStatic\CLI\SyncCommand());
        }
    }

    /**
     * Strip output that only works with PHP/WordPress behind it from the pages we
     * render for the static copy. Currently the WordPress emoji loader: its
     * `wp-emoji-release.min.js` is referenced only from inline JS (so it can't be
     * mirrored and 404s on the static host), and modern browsers render emoji
     * natively without it. Applies to our render requests only — never the live
     * front end. Disable with the `bs_strip_emoji` filter.
     */
    public static function prepare_render_output(): void {
        /** Filters whether the WordPress emoji loader is stripped from the static output. */
        if (!apply_filters('bs_strip_emoji', true)) {
            return;
        }

        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    }

    /**
     * Register REST API controllers.
     */
    public static function register_rest_routes(): void {
        ConnectionController::register();
        DestinationsController::register();
        StatusController::register();
        SyncController::register();
    }
}
