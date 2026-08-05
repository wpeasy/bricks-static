<?php
/**
 * Plugin bootstrap.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic;

use WPEasy\BricksStatic\Abilities\AbilityRegistry;
use WPEasy\BricksStatic\Admin\Editor;
use WPEasy\BricksStatic\Admin\Menu;
use WPEasy\BricksStatic\Export\ExportDownload;
use WPEasy\BricksStatic\Frontend\Fab;
use WPEasy\BricksStatic\Render\LinkDeployReplacer;
use WPEasy\BricksStatic\Render\MediaDeployReplacer;
use WPEasy\BricksStatic\Render\PageRenderer;
use WPEasy\BricksStatic\Render\TextDeployReplacer;
use WPEasy\BricksStatic\Render\VideoDeployReplacer;
use WPEasy\BricksStatic\Settings\Destinations;
use WPEasy\BricksStatic\Sitemap\SitemapGenerator;
use WPEasy\BricksStatic\Sync\Pipeline;
use WPEasy\BricksStatic\REST\ConnectionController;
use WPEasy\BricksStatic\REST\DestinationsController;
use WPEasy\BricksStatic\REST\EditorController;
use WPEasy\BricksStatic\REST\ExportController;
use WPEasy\BricksStatic\REST\LinksController;
use WPEasy\BricksStatic\REST\MediaController;
use WPEasy\BricksStatic\REST\SitemapController;
use WPEasy\BricksStatic\REST\StatusController;
use WPEasy\BricksStatic\REST\SyncController;
use WPEasy\BricksStatic\REST\VideosController;

defined('ABSPATH') || exit;

/**
 * Wires up the plugin's subsystems.
 */
final class Plugin {

    /**
     * Runs on plugin activation (`register_activation_hook`, `bricks-static.php`).
     * Resets the first-run setup-wizard flag so deactivating and reactivating
     * the plugin always shows it again — e.g. re-testing onboarding, or a fresh
     * clone of a site that already has the option set.
     */
    public static function activate(): void {
        delete_option('bs_wizard_seen');
    }

    /**
     * Initialise the plugin.
     */
    public static function init(): void {
        // Migrate the legacy single destination into the destinations list once.
        Destinations::ensure_migrated();

        self::register_pipeline();

        if (is_admin()) {
            Menu::init();
            // Per-post "Include" metabox — self-gates to `manual` discovery mode.
            Editor::init();
        }

        // Frontend "Sync this page" FAB (self-gates to admins on public pages).
        Fab::init();

        // Watch content edits so the dashboard can flag the render stale.
        \WPEasy\BricksStatic\Sync\ChangeTracker::init();

        // Expose abilities to the WordPress Abilities API for MCP/AI discovery
        // (no-op on WP < 6.9; read-only always, actions behind opt-in toggles).
        AbilityRegistry::init();

        add_action('rest_api_init', [self::class, 'register_rest_routes']);

        // On our own loopback render requests only, tidy the captured output so
        // the static copy doesn't ship broken references. (Head cruft — feed/
        // oEmbed links, generator meta — is cleaned deterministically on the
        // captured HTML string in the render pipeline; see Render\HeadCleaner.)
        if (PageRenderer::is_render_request()) {
            add_action('init', [self::class, 'prepare_render_output']);
        }

        // Let our own render requests through Bricks' maintenance / coming-soon
        // mode. Registered before `wp` (where Bricks applies it at priority 9).
        add_filter('bricks/maintenance/should_apply', [self::class, 'allow_render_through_maintenance']);

        if (defined('WP_CLI') && \WP_CLI) {
            \WP_CLI::add_command('bricks-static', new \WPEasy\BricksStatic\CLI\SyncCommand());
        }
    }

    /**
     * Register the deploy-pipeline replacers (text, media, links, videos) and the
     * sitemap/robots.txt extra-file emitter onto {@see Pipeline}.
     */
    private static function register_pipeline(): void {
        Pipeline::register_replacer(new TextDeployReplacer());
        Pipeline::register_replacer(new MediaDeployReplacer());
        Pipeline::register_replacer(new LinkDeployReplacer());
        Pipeline::register_replacer(new VideoDeployReplacer());

        Pipeline::register_extra_files(static function (array $page_urls): array {
            if (!apply_filters('bs_generate_sitemaps', true)) {
                return [];
            }

            return SitemapGenerator::from_urls($page_urls);
        });
    }

    /**
     * Filters `bricks/maintenance/should_apply` (Bricks 2.0+) so a static render
     * captures the real page, not the maintenance screen.
     *
     * With maintenance mode on, Bricks serves its maintenance template to every
     * visitor — including our loopback render, which would otherwise mirror that
     * screen over the whole site ("coming soon" returns HTTP 200, so it wouldn't
     * even fail loudly). The bypass requires the shared-secret header, so only a
     * render this site started gets through; a visitor spoofing the user agent
     * still sees the maintenance page.
     *
     * @param bool $apply Whether Bricks intends to apply maintenance mode.
     * @return bool
     */
    public static function allow_render_through_maintenance($apply): bool {
        if (!PageRenderer::is_trusted_render_request()) {
            return (bool) $apply;
        }

        /**
         * Filters whether static renders bypass maintenance / coming-soon mode.
         * Return false to have renders capture the maintenance screen instead.
         *
         * @param bool $bypass Whether to bypass.
         */
        return apply_filters('bs_bypass_maintenance', true) ? false : (bool) $apply;
    }

    /**
     * Strip output that only works with PHP/WordPress behind it from the pages we
     * render for the static copy. Currently the WordPress emoji loader: its
     * `wp-emoji-release.min.js` is referenced only from inline JS (so it can't be
     * mirrored and 404s on the static host), and modern browsers render emoji
     * natively without it. Applies to our render requests only — never the live
     * front end. Disable with the `bs_strip_emoji` filter.
     */
    public static function prepare_render_output(): void {
        /** Filters whether the WordPress emoji loader is stripped from the static output. */
        if (!apply_filters('bs_strip_emoji', true)) {
            return;
        }

        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    }

    /**
     * Register REST API controllers.
     */
    public static function register_rest_routes(): void {
        ConnectionController::register();
        DestinationsController::register();
        StatusController::register();
        SyncController::register();
        EditorController::register();
        MediaController::register();
        ExportController::register();
        ExportDownload::register();
        LinksController::register();
        VideosController::register();
        SitemapController::register();
    }
}
