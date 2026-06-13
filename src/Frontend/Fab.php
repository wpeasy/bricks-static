<?php
/**
 * Frontend "Sync this page" floating action button.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Frontend;

use WPEasy\BricksStatic\Render\PageRenderer;

defined('ABSPATH') || exit;

/**
 * Enqueues the draggable frontend FAB + modal, but only on real public pages and
 * only for users who can manage the plugin. Never runs in wp-admin or inside the
 * Bricks builder (main window or preview iframe), per the preview-iframe rule.
 */
final class Fab {

    /**
     * Script handle for the frontend bundle (loaded as an ES module).
     */
    private const SCRIPT_HANDLE = 'bs-frontend';

    /**
     * Manifest entry key (Vite input name).
     */
    private const ENTRY = 'src-svelte/frontend/main.ts';

    /**
     * Register hooks.
     */
    public static function init(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        add_filter('script_loader_tag', [self::class, 'script_as_module'], 10, 2);
    }

    /**
     * Whether the FAB should load on the current request.
     */
    private static function should_load(): bool {
        // Never inject the button into a static render. The loopback render request
        // is unauthenticated (so the capability check below already excludes it),
        // but gate on it explicitly too so the FAB can't leak into captured HTML
        // even if a filter ever adds auth to the render request.
        if (PageRenderer::is_render_request()) {
            return false;
        }

        if (is_admin() || !current_user_can('manage_options')) {
            return false;
        }

        // Never inside the Bricks builder (its main window or the preview iframe) —
        // the iframe renders the user's content and must not run our feature code.
        if (function_exists('bricks_is_builder_iframe') && bricks_is_builder_iframe()) {
            return false;
        }
        if (function_exists('bricks_is_builder_main') && bricks_is_builder_main()) {
            return false;
        }

        return true;
    }

    /**
     * Enqueue the built bundle (CSS + JS) and pass the page context to JS.
     */
    public static function enqueue(): void {
        if (!self::should_load()) {
            return;
        }

        $entry = self::manifest_entry();
        if ($entry === null) {
            return; // Assets not built — fail silent on the frontend.
        }

        foreach ((array) ($entry['css'] ?? []) as $i => $css_file) {
            wp_enqueue_style('bs-frontend-' . $i, BS_PLUGIN_URL . 'assets/dist/' . $css_file, [], BS_VERSION);
        }

        wp_enqueue_script(self::SCRIPT_HANDLE, BS_PLUGIN_URL . 'assets/dist/' . $entry['file'], [], BS_VERSION, true);

        wp_localize_script(self::SCRIPT_HANDLE, 'bsFabData', [
            'restUrl' => esc_url_raw(rest_url('bs/v1')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'pageUrl' => self::current_url(),
        ]);
    }

    /**
     * The canonical URL of the page being viewed (query string dropped — the
     * static export keys pages by path).
     */
    private static function current_url(): string {
        global $wp;
        $path = isset($wp->request) ? (string) $wp->request : '';

        return $path !== '' ? home_url(user_trailingslashit($path)) : home_url('/');
    }

    /**
     * Output the frontend script tag as an ES module.
     *
     * @param string $tag    The script tag HTML.
     * @param string $handle The script handle.
     */
    public static function script_as_module(string $tag, string $handle): string {
        if ($handle !== self::SCRIPT_HANDLE) {
            return $tag;
        }

        return str_replace('<script ', '<script type="module" ', $tag);
    }

    /**
     * Read the frontend entry from the Vite manifest.
     *
     * @return array<string,mixed>|null
     */
    private static function manifest_entry(): ?array {
        $path = BS_PLUGIN_DIR . 'assets/dist/.vite/manifest.json';
        if (!is_readable($path)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($path), true);
        $entry    = is_array($manifest) ? ($manifest[self::ENTRY] ?? null) : null;

        return is_array($entry) ? $entry : null;
    }
}
