<?php
/**
 * Enqueues the Pro dashboard bundle onto the Free plugin's dashboard page.
 *
 * The Pro JS is a separate ES-module bundle that depends on the Free dashboard
 * bundle (so window.BS exists before it registers its panels). Only loaded on
 * the Free dashboard page, and only when the addon is licensed (Bootstrap gates
 * the call).
 *
 * @package WPEasy\BricksStaticPro
 * @since   0.0.4
 */

declare(strict_types=1);

namespace WPEasy\BricksStaticPro\Admin;

use WPEasy\BricksStatic\Admin\Menu;
use WPEasy\BricksStaticPro\Support\I18n;

defined('ABSPATH') || exit;

/**
 * Pro dashboard asset loader.
 */
final class ProMenu {

    /**
     * Script/style handle for the Pro bundle.
     */
    private const HANDLE = 'bsp-dashboard';

    /**
     * Register hooks.
     */
    public static function init(): void {
        // Priority 11 so it runs just after the Free Menu's enqueue (priority 10).
        add_action('admin_enqueue_scripts', [self::class, 'enqueue'], 11);
        add_filter('script_loader_tag', [self::class, 'script_as_module'], 10, 3);
    }

    /**
     * Enqueue the Pro bundle on the Free dashboard page only.
     *
     * @param string $hook Current admin page hook suffix.
     */
    public static function enqueue(string $hook): void {
        if (!class_exists(Menu::class) || $hook !== Menu::dashboard_hook()) {
            return;
        }

        $entry = self::manifest_entry('pro/src-svelte/pro/main.ts');
        if ($entry === null) {
            return;
        }

        // Pro panels mount inside the Free dashboard's `.ab-ui`, so ab-ui's
        // styles (loaded by Free) cover their `--ab-*` tokens; no extra dep.
        foreach ((array) ($entry['css'] ?? []) as $i => $css_file) {
            wp_enqueue_style(self::HANDLE . '-' . $i, BSP_PLUGIN_URL . 'assets/dist/' . $css_file, [], BSP_VERSION);
        }

        // Depends on the Free dashboard handle so it loads AFTER window.BS exists.
        wp_enqueue_script(self::HANDLE, BSP_PLUGIN_URL . 'assets/dist/' . $entry['file'], ['bs-dashboard'], BSP_VERSION, true);

        // Pro UI strings (already translated server-side). The Free shared/i18n.ts
        // helper merges window.bspData.i18n over window.bsData.i18n.
        wp_localize_script(self::HANDLE, 'bspData', [
            'i18n' => I18n::all(),
        ]);
    }

    /**
     * Output the Pro bundle's script tag as an ES module.
     *
     * @param string $tag    Script tag HTML.
     * @param string $handle Script handle.
     * @param string $src    Script source URL.
     */
    public static function script_as_module(string $tag, string $handle, string $src): string {
        if ($handle !== self::HANDLE) {
            return $tag;
        }

        return sprintf(
            '<script type="module" src="%s" id="%s-js"></script>' . "\n",
            esc_url($src),
            esc_attr($handle)
        );
    }

    /**
     * Read an entry from the Pro Vite manifest.
     *
     * @param string $entry_key Manifest input key.
     * @return array<string,mixed>|null
     */
    private static function manifest_entry(string $entry_key): ?array {
        $path = BSP_PLUGIN_DIR . 'assets/dist/.vite/manifest.json';

        if (!is_readable($path)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($path), true);
        $entry    = is_array($manifest) ? ($manifest[$entry_key] ?? null) : null;

        return is_array($entry) ? $entry : null;
    }
}
