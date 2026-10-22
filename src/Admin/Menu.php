<?php
/**
 * Admin menu + page registration.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Admin;

use WPEasy\BricksStatic\Support\Assets;
use WPEasy\BricksStatic\Support\I18n;

defined('ABSPATH') || exit;

/**
 * Registers the top-level "Bricks Static" admin page and its assets.
 */
final class Menu {

    /**
     * Admin menu slug.
     */
    private const MENU_SLUG = 'bricks-static';

    /**
     * Documentation submenu slug.
     */
    private const DOCS_SLUG = 'bricks-static-docs';

    /**
     * Capability required to view the page.
     */
    private const CAPABILITY = 'manage_options';

    /**
     * Script handle for the dashboard bundle (loaded as an ES module).
     */
    private const SCRIPT_HANDLE = 'bricks-static-dashboard';

    /**
     * Script handle for the documentation bundle.
     */
    private const DOCS_HANDLE = 'bricks-static-docs';

    /**
     * Page hook suffixes (dashboard, docs), used to scope asset enqueuing.
     *
     * @var string
     */
    private static string $page_hook = '';

    /**
     * @var string
     */
    private static string $docs_hook = '';

    /**
     * Register hooks.
     */
    public static function init(): void {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_filter('script_loader_tag', [self::class, 'script_as_module'], 10, 3);
    }

    /**
     * Register the top-level admin menu page.
     */
    public static function register_menu(): void {
        self::$page_hook = (string) add_menu_page(
            __('Bricks Static', 'bricks-static'),
            __('Bricks Static', 'bricks-static'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [self::class, 'render_page'],
            'dashicons-media-code',
            58
        );

        // Rename the auto-created first submenu (defaults to the menu title) to
        // "Dashboard", then add the Documentation page.
        add_submenu_page(
            self::MENU_SLUG,
            __('Bricks Static', 'bricks-static'),
            __('Dashboard', 'bricks-static'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [self::class, 'render_page']
        );

        self::$docs_hook = (string) add_submenu_page(
            self::MENU_SLUG,
            __('Documentation', 'bricks-static'),
            __('Documentation', 'bricks-static'),
            self::CAPABILITY,
            self::DOCS_SLUG,
            [self::class, 'render_docs']
        );
    }

    /**
     * Enqueue the base framework CSS and built dashboard bundle on our page only.
     *
     * @param string $hook The current admin page hook suffix.
     */
    public static function enqueue_assets(string $hook): void {
        if ($hook === self::$page_hook) {
            // The browser-driven sync needs PHP workers free for its loopback page
            // renders. The admin heartbeat would periodically consume one, stalling
            // the render on low-worker hosts (e.g. Local). It isn't needed here.
            wp_deregister_script('heartbeat');
            // The media replacer opens the WordPress media library (wp.media).
            wp_enqueue_media();
            self::enqueue_app('src-svelte/dashboard/main.ts', self::SCRIPT_HANDLE, [
                // Whether the setup wizard has never been shown/dismissed yet —
                // dashboard-only, so it never leaks onto the docs page's bricksStaticData.
                'isFirstRun' => get_option('bricks_static_wizard_seen', '0') === '0',
            ]);
        } elseif ($hook === self::$docs_hook) {
            self::enqueue_app('src-svelte/docs/main.ts', self::DOCS_HANDLE);
        }
    }

    /**
     * Enqueue a built Svelte app (framework CSS + the entry's CSS chunks + JS),
     * reading the hashed filenames from the Vite manifest, plus the REST data.
     *
     * @param string               $entry_key Vite manifest input key.
     * @param string               $handle    Script handle (also the CSS handle prefix).
     * @param array<string,mixed>  $extra     Extra key/value pairs merged into `bricksStaticData`.
     */
    private static function enqueue_app(string $entry_key, string $handle, array $extra = []): void {
        $entry = self::manifest_entry($entry_key);
        if ($entry === null) {
            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-error"><p>'
                    . esc_html__('Bricks Static: the admin assets are not built. Run "npm run build" in the plugin directory.', 'bricks-static')
                    . '</p></div>';
            });
            return;
        }

        // Enqueue the entry's CSS AND every transitively-imported chunk's CSS.
        // A plain CSS import shared by 2+ entries (e.g. `ab-ui/styles`, now used
        // by the dashboard AND editor) is split by Vite into a shared chunk whose
        // CSS lives outside this entry's own `css[]` — walk imports so it loads.
        foreach (Assets::css_files($entry_key) as $i => $css_file) {
            wp_enqueue_style($handle . '-' . $i, BRICKS_STATIC_PLUGIN_URL . 'assets/dist/' . $css_file, [], BRICKS_STATIC_VERSION);
        }

        wp_enqueue_script($handle, BRICKS_STATIC_PLUGIN_URL . 'assets/dist/' . $entry['file'], [], BRICKS_STATIC_VERSION, true);

        wp_localize_script($handle, 'bricksStaticData', array_merge([
            'restUrl' => esc_url_raw(rest_url('bricks-static/v1')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'version' => BRICKS_STATIC_VERSION,
            // Translated UI strings for the Svelte bundle (shared/i18n.ts).
            'i18n'    => I18n::all(),
        ], $extra));
    }

    /**
     * Output the dashboard script tag as an ES module.
     *
     * @param string $tag    The script tag HTML.
     * @param string $handle The script handle.
     * @param string $src    The script source URL.
     */
    public static function script_as_module(string $tag, string $handle, string $src): string {
        if ($handle !== self::SCRIPT_HANDLE && $handle !== self::DOCS_HANDLE) {
            return $tag;
        }

        // wp_get_script_tag() escapes the attributes itself.
        return wp_get_script_tag([
            'type' => 'module',
            'src'  => $src,
            'id'   => $handle . '-js',
        ]);
    }

    /**
     * Read an entry from the Vite manifest.
     *
     * @param string $entry_key Manifest input key.
     * @return array<string,mixed>|null Entry data, or null if the build is missing.
     */
    private static function manifest_entry(string $entry_key): ?array {
        $path = BRICKS_STATIC_PLUGIN_DIR . 'assets/dist/.vite/manifest.json';

        if (!is_readable($path)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($path), true);
        $entry    = is_array($manifest) ? ($manifest[$entry_key] ?? null) : null;

        return is_array($entry) ? $entry : null;
    }

    /**
     * Render the dashboard page.
     */
    public static function render_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        require BRICKS_STATIC_PLUGIN_DIR . 'templates/admin/page.php';
    }

    /**
     * Render the documentation page.
     */
    public static function render_docs(): void {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        require BRICKS_STATIC_PLUGIN_DIR . 'templates/admin/docs.php';
    }
}
