<?php
/**
 * Admin menu + page registration.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Admin;

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
     * Capability required to view the page.
     */
    private const CAPABILITY = 'manage_options';

    /**
     * Script handle for the dashboard bundle (loaded as an ES module).
     */
    private const SCRIPT_HANDLE = 'bs-dashboard';

    /**
     * The page hook suffix returned by add_menu_page(), used to scope asset
     * enqueuing to our page only.
     *
     * @var string
     */
    private static string $page_hook = '';

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
    }

    /**
     * Enqueue the base framework CSS and built dashboard bundle on our page only.
     *
     * @param string $hook The current admin page hook suffix.
     */
    public static function enqueue_assets(string $hook): void {
        if ($hook !== self::$page_hook) {
            return;
        }

        // The browser-driven sync needs PHP workers free for its loopback page
        // renders. The admin heartbeat would periodically consume one, stalling
        // the render on low-worker hosts (e.g. Local). It isn't needed here.
        wp_deregister_script('heartbeat');

        wp_enqueue_style(
            'bs-framework',
            BS_PLUGIN_URL . 'assets/css/bs-framework.css',
            [],
            BS_VERSION
        );

        $entry = self::manifest_entry();

        if ($entry === null) {
            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-error"><p>'
                    . esc_html__('Bricks Static: the dashboard assets are not built. Run "npm run build" in the plugin directory.', 'bricks-static')
                    . '</p></div>';
            });
            return;
        }

        foreach ((array) ($entry['css'] ?? []) as $i => $css_file) {
            wp_enqueue_style(
                'bs-dashboard-' . $i,
                BS_PLUGIN_URL . 'assets/dist/' . $css_file,
                ['bs-framework'],
                BS_VERSION
            );
        }

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            BS_PLUGIN_URL . 'assets/dist/' . $entry['file'],
            [],
            BS_VERSION,
            true
        );

        wp_localize_script(self::SCRIPT_HANDLE, 'bsData', [
            'restUrl' => esc_url_raw(rest_url('bs/v1')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    /**
     * Output the dashboard script tag as an ES module.
     *
     * @param string $tag    The script tag HTML.
     * @param string $handle The script handle.
     * @param string $src    The script source URL.
     */
    public static function script_as_module(string $tag, string $handle, string $src): string {
        if ($handle !== self::SCRIPT_HANDLE) {
            return $tag;
        }

        return sprintf(
            '<script type="module" src="%s" id="%s-js"></script>' . "\n",
            esc_url($src),
            esc_attr($handle)
        );
    }

    /**
     * Read the dashboard entry from the Vite manifest.
     *
     * @return array<string,mixed>|null Entry data, or null if the build is missing.
     */
    private static function manifest_entry(): ?array {
        $path = BS_PLUGIN_DIR . 'assets/dist/.vite/manifest.json';

        if (!is_readable($path)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($path), true);
        $entry    = is_array($manifest) ? ($manifest['src-svelte/dashboard/main.ts'] ?? null) : null;

        return is_array($entry) ? $entry : null;
    }

    /**
     * Render the admin page (placeholder for now).
     */
    public static function render_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        require BS_PLUGIN_DIR . 'templates/admin/page.php';
    }
}
