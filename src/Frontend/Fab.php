<?php
/**
 * Frontend "Sync this page" floating action button.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Frontend;

use WPEasy\BricksStatic\Discovery\UrlCollector;
use WPEasy\BricksStatic\Render\PageRenderer;
use WPEasy\BricksStatic\Support\Assets;

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

        // Admin toggle (Bricks Static dashboard app bar), on by default. Stored as
        // '1'/'0' so an explicit "off" isn't swallowed by get_option's default.
        if (get_option('bs_fab_enabled', '1') === '0') {
            return false;
        }

        // Never inside the preview iframe — it renders the user's content and must
        // not run our feature code. The builder MAIN window is allowed (the FAB
        // floats over the editor chrome there).
        if (function_exists('bricks_is_builder_iframe') && bricks_is_builder_iframe()) {
            return false;
        }

        return true;
    }

    /**
     * Whether the current request is the Bricks builder's main (editor) window —
     * the FAB triggers a builder save before syncing there.
     */
    private static function in_editor(): bool {
        return function_exists('bricks_is_builder_main') && bricks_is_builder_main();
    }

    /**
     * Enqueue the built bundle (CSS + JS) and pass the page context to JS.
     */
    public static function enqueue(): void {
        if (!self::should_load()) {
            return;
        }

        $entry = Assets::entry(self::ENTRY);
        if ($entry === null) {
            return; // Assets not built — fail silent on the frontend.
        }

        // Includes the shared SyncPanel modal's CSS chunk (transitive) + the ab-ui
        // base stylesheet, so the modal is themed off the dashboard theme on the
        // front end too. ab-ui CSS is fully `.ab-ui`-scoped — it cannot affect the
        // visitor-facing page content.
        Assets::enqueue_ab_ui_css();
        Assets::enqueue_css(self::ENTRY);
        wp_enqueue_script(self::SCRIPT_HANDLE, BS_PLUGIN_URL . 'assets/dist/' . $entry['file'], [], BS_VERSION, true);

        // In `manual` discovery mode the modal also shows a per-post "Include"
        // switch, so the page can be opted in/out from the Bricks editor / front
        // end without opening the WP post editor.
        $manual = UrlCollector::mode() === 'manual';

        // Always resolve the post id (not just in manual mode): the modal sends it
        // to /sync/page-status so single-page sync can be gated to published
        // pages. Without it the server's url_to_postid() fallback returns 0 for a
        // draft (no permalink rewrite), and the draft looks publishable.
        $post_id = self::resolve_post_id();

        wp_localize_script(self::SCRIPT_HANDLE, 'bsFabData', [
            'restUrl'       => esc_url_raw(rest_url('bs/v1')),
            'nonce'         => wp_create_nonce('wp_rest'),
            'pageUrl'       => self::current_url(),
            'inEditor'      => self::in_editor(),
            'manualMode'    => $manual,
            'postId'        => $post_id,
            'included'      => $post_id > 0 ? UrlCollector::is_included($post_id) : false,
            'effective'     => $post_id > 0 ? UrlCollector::is_effective($post_id) : false,
            'includedCount' => $manual ? UrlCollector::effective_count() : 0,
            'savedCount'    => $manual ? UrlCollector::included_count() : 0,
        ]);
    }

    /**
     * Best-effort resolve the post id for the current page (for the manual-mode
     * Include switch). The queried object on a singular page, else a reverse
     * lookup from the URL.
     */
    private static function resolve_post_id(): int {
        $id = is_singular() ? (int) get_queried_object_id() : 0;
        if ($id <= 0) {
            $id = (int) url_to_postid(self::current_url());
        }

        return $id;
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
}
