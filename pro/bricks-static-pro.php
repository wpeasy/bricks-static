<?php
/**
 * Plugin Name:       Bricks Static Pro
 * Plugin URI:        https://brxprod.com/bricks-static
 * Description:       Pro features for Bricks Static — multiple destinations, advanced replacements (media/links/videos/data), gzip pre-compression, pruning, and sitemaps.
 * Version:           0.0.4-beta
 * Requires at least: 6.5
 * Tested up to:      7.0
 * Requires PHP:      8.0
 * Requires Plugins:  bricks-static
 * Author:            Alan Blair <alan@alanblair.co>
 * Author URI:        https://brxprod.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bricks-static-pro
 * Domain Path:       /languages
 * Network:           false
 * Update URI:        false
 *
 * @package WPEasy\BricksStaticPro
 */

declare(strict_types=1);

namespace WPEasy\BricksStaticPro;

defined('ABSPATH') || exit;

/**
 * Plugin constants.
 */
define('BSP_VERSION', '0.0.4-beta');
define('BSP_PLUGIN_FILE', __FILE__);
define('BSP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BSP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BSP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The minimum Free (Bricks Static) version that ships the extension seams this
 * addon plugs into. Bootstrap refuses to register features against an older Free.
 */
define('BSP_MIN_FREE', '0.0.4-beta');

/**
 * Licensing configuration (FluentCart) — see FLUENT_LICENSING.md.
 *
 * The same FluentCart server as Bricks Productivity (brxprod.com). The item id
 * is filled in AFTER the first release is added as a product in FluentCart;
 * until then the addon stays unlicensed (free behaviour) gracefully.
 */
define('BSP_LICENSE_ITEM_ID', '[PENDING]'); // TODO: set from the FluentCart product once created.
define('BSP_LICENSE_API_URL', 'https://brxprod.com/');
define('BSP_LICENSE_SLUG', 'bricks-static-pro');
define('BSP_LICENSE_SETTINGS_KEY', 'bricks_static_pro_license_settings');
define('BSP_LICENSE_MENU_TYPE', 'submenu');
define('BSP_LICENSE_MENU_TITLE', 'License');
define('BSP_LICENSE_PAGE_TITLE', 'Bricks Static Pro - License');
define('BSP_LICENSE_PURCHASE_URL', 'https://brxprod.com/bricks-static/');
define('BSP_LICENSE_ACCOUNT_URL', 'https://brxprod.com/account/');

/**
 * PSR-4 autoloader for the Pro namespace, mapping to this folder's src/.
 *
 * Self-contained (independent of Composer) and entirely separate from Free's
 * autoloader, so the Free plugin never loads a Pro class.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = __NAMESPACE__ . '\\';
    $len    = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file     = BSP_PLUGIN_DIR . 'src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_readable($file)) {
        require $file;
    }
});

/**
 * Boot once Free has finished initialising. `bs_loaded` is fired at the end of
 * the Free plugin's Plugin::init(), guaranteeing Free's seams exist. The
 * Requires Plugins header keeps Free active, but we still guard defensively.
 */
add_action('bs_loaded', static function (): void {
    if (class_exists(__NAMESPACE__ . '\\Bootstrap')) {
        Bootstrap::init();
    }
}, 10);
