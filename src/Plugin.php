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
use WPEasy\BricksStatic\REST\ConnectionController;
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
        if (is_admin()) {
            Menu::init();
        }

        add_action('rest_api_init', [self::class, 'register_rest_routes']);

        if (defined('WP_CLI') && \WP_CLI) {
            \WP_CLI::add_command('bricks-static', new \WPEasy\BricksStatic\CLI\SyncCommand());
        }
    }

    /**
     * Register REST API controllers.
     */
    public static function register_rest_routes(): void {
        ConnectionController::register();
        StatusController::register();
        SyncController::register();
    }
}
