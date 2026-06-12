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
    }
}
