<?php
/**
 * Plugin Name:       Bricks Static
 * Plugin URI:        https://alanblair.co/bricks-static
 * Description:       Generate and serve static HTML versions of Bricks-built pages for performance.
 * Version:           0.0.1-beta
 * Requires at least: 6.5
 * Tested up to:      7.0
 * Requires PHP:      8.0
 * Author:            Alan Blair <alan@alanblair.co>
 * Author URI:        https://alanblair.co
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bricks-static
 * Domain Path:       /languages
 * Network:           false
 * Update URI:        false
 *
 * @package WPEasy\BricksStatic
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic;

defined('ABSPATH') || exit;

/**
 * Plugin constants.
 */
define('BS_VERSION', '0.0.1-beta');
define('BS_PLUGIN_FILE', __FILE__);
define('BS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * PSR-4 autoloader for the plugin namespace.
 *
 * Kept self-contained (no Composer `vendor/autoload.php` required at runtime)
 * so the plugin works on a fresh install. Composer is only used for tooling.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = __NAMESPACE__ . '\\';
    $len    = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return; // Not ours — let other autoloaders handle it.
    }

    $relative = substr($class, $len);
    $file     = BS_PLUGIN_DIR . 'src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_readable($file)) {
        require $file;
    }
});

/**
 * Boot the plugin once all plugins are loaded.
 */
add_action('plugins_loaded', static function (): void {
    Plugin::init();
});
