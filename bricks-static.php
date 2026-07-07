<?php
/**
 * Plugin Name:       Bricks Static
 * Plugin URI:        https://alanblair.co/bricks-static
 * Description:       Generate and serve static HTML versions of Bricks-built pages for performance.
 * Version:           1.0.4
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
define('BS_VERSION', '1.0.4');
define('BS_PLUGIN_FILE', __FILE__);
define('BS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Composer autoloader for bundled dependencies (phpseclib).
 *
 * Shipped in the plugin zip so end users don't need Composer. Loaded if present;
 * the self-contained autoloader below still handles the plugin's own classes.
 */
if (is_readable(BS_PLUGIN_DIR . 'vendor/autoload.php')) {
    require BS_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * PSR-4 autoloader for the plugin namespace.
 *
 * Kept self-contained (independent of Composer) so the plugin's own classes load
 * even if `vendor/` is absent.
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
 * Load translations from /languages (bundled .mo files). On `init` per WP 6.7+,
 * which warns when a textdomain is loaded too early.
 */
add_action('init', static function (): void {
    load_plugin_textdomain('bricks-static', false, dirname(BS_PLUGIN_BASENAME) . '/languages');
});

/**
 * Boot the plugin once all plugins are loaded.
 */
add_action('plugins_loaded', static function (): void {
    Plugin::init();
});
