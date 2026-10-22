<?php
/**
 * Uninstall cleanup.
 *
 * Runs when the plugin is deleted from the WordPress admin. Removes everything
 * the plugin stores: its options (settings, destinations — including saved
 * passwords and API tokens — job state, manifests), its transients, the
 * per-post "include in export" meta, and the staging cache directory.
 *
 * Files already deployed to destinations are not touched.
 *
 * @package WPEasy\BricksStatic
 */

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

// The plugin isn't loaded during uninstall; register its autoloader so the
// cache path is resolved exactly as at runtime (constant → filter → default).
spl_autoload_register(static function (string $class): void {
    $prefix = 'WPEasy\\BricksStatic\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});

/**
 * Remove one site's data.
 */
function bricks_static_uninstall_site(): void {
    global $wpdb;

    // Options + transients. Every key the plugin writes starts with
    // `bricks_static_` (transients: `_transient[_timeout]_bricks_static_…`).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off bulk delete on uninstall; no cache to keep.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('bricks_static_') . '%',
            $wpdb->esc_like('_transient_bricks_static_') . '%',
            $wpdb->esc_like('_transient_timeout_bricks_static_') . '%'
        )
    );
    wp_cache_flush();

    delete_post_meta_by_key('_bricks_static_manual_include');

    // Staging cache (rendered pages, packages, exports). Only removed when it is
    // the plugin's own folder — a custom BRICKS_STATIC_CACHE_DIR / filter could
    // point somewhere shared, so anything not named `bricks-static` is left alone.
    $dir = \WPEasy\BricksStatic\Support\Paths::cache_dir();
    if ($dir !== '' && basename($dir) === 'bricks-static' && is_dir($dir)) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        (new WP_Filesystem_Direct(null))->rmdir($dir, true);
    }
}

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $bricks_static_site_id) {
        switch_to_blog((int) $bricks_static_site_id);
        bricks_static_uninstall_site();
        restore_current_blog();
    }
} else {
    bricks_static_uninstall_site();
}
