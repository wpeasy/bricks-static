<?php
/**
 * Pro addon bootstrap — wires the Pro features into the Free plugin's seams.
 *
 * Runs on the Free plugin's `bs_loaded` action (guaranteeing Free's hooks and
 * registries exist). Licensing is always booted (so the user can activate a
 * key); feature wiring happens only while the license is valid/grace, so an
 * expired or absent license degrades cleanly to Free behaviour without ever
 * deleting the user's saved Pro data.
 *
 * @package WPEasy\BricksStaticPro
 * @since   0.0.4
 */

declare(strict_types=1);

namespace WPEasy\BricksStaticPro;

use WPEasy\BricksStatic\Support\Edition;
use WPEasy\BricksStatic\Sync\Pipeline;
use WPEasy\BricksStaticPro\Admin\ProMenu;
use WPEasy\BricksStaticPro\Licensing\FluentLicensing;
use WPEasy\BricksStaticPro\Licensing\LicenseEnforcer;
use WPEasy\BricksStaticPro\Licensing\LicenseSettings;
use WPEasy\BricksStaticPro\Render\LinkDeployReplacer;
use WPEasy\BricksStaticPro\Render\MediaDeployReplacer;
use WPEasy\BricksStaticPro\Render\VideoDeployReplacer;
use WPEasy\BricksStaticPro\REST\LinksController;
use WPEasy\BricksStaticPro\REST\MediaController;
use WPEasy\BricksStaticPro\REST\SitemapController;
use WPEasy\BricksStaticPro\REST\VideosController;
use WPEasy\BricksStaticPro\Sitemap\SitemapGenerator;

defined('ABSPATH') || exit;

/**
 * Boots the Pro addon.
 */
final class Bootstrap {

    /**
     * Entry point (hooked to `bs_loaded`).
     */
    public static function init(): void {
        // The Free plugin must be recent enough to expose the seams we plug into.
        if (!defined('BS_VERSION') || version_compare((string) BS_VERSION, BSP_MIN_FREE, '<')) {
            add_action('admin_notices', [self::class, 'notice_free_too_old']);
            return;
        }

        // Load Pro translations from pro/languages (bundled .mo files).
        add_action('init', static function (): void {
            load_plugin_textdomain('bricks-static-pro', false, dirname(BSP_PLUGIN_BASENAME) . '/languages');
        });

        // Advertise the Pro version to Free (dashboard version badge) whenever the
        // addon is active, regardless of license state.
        add_filter('bs_pro_version', static fn(): string => BSP_VERSION);

        // Always boot licensing so the activation page works and the edition
        // filters Free reads are present.
        self::boot_licensing();

        // Pro feature wiring — only while licensed (valid/grace). When expired or
        // unlicensed, Edition::is_pro() is false: no replacers/controllers are
        // registered, gzip/sitemaps/prune are off and the destination cap is 1.
        // Saved Pro data (replacements, extra destinations) is preserved.
        if (Edition::is_pro()) {
            self::register_pipeline();
            add_action('bs_register_rest_routes', [self::class, 'register_rest_routes']);
            ProMenu::init();
        }
    }

    /**
     * Register the FluentCart licensing provider, the enforcer, the admin
     * license page, and the `bs_license_*` filters the Free plugin reads to
     * resolve the active edition.
     */
    private static function boot_licensing(): void {
        if (!class_exists(FluentLicensing::class)) {
            return;
        }

        (new FluentLicensing())->register([
            'version'      => BSP_VERSION,
            'item_id'      => BSP_LICENSE_ITEM_ID,
            'basename'     => BSP_PLUGIN_BASENAME,
            'api_url'      => BSP_LICENSE_API_URL,
            'slug'         => BSP_LICENSE_SLUG,
            'settings_key' => BSP_LICENSE_SETTINGS_KEY,
        ]);

        if (class_exists(LicenseEnforcer::class)) {
            LicenseEnforcer::getInstance()->init();
        }

        // Master switch: Free's Support\License reads these. 'pro' only while the
        // license can use features (valid/grace) — expiry hard-gates to 'free'.
        add_filter('bs_license_edition', static function (): string {
            return self::licensed() ? 'pro' : 'free';
        });

        add_filter('bs_license_status', static function ($status) {
            if (!class_exists(LicenseEnforcer::class)) {
                return $status;
            }
            $enf = LicenseEnforcer::getInstance();

            return [
                'valid'   => $enf->canUseFeatures(),
                'state'   => $enf->getState(),
                'expires' => '',
            ];
        });

        self::register_license_page();
    }

    /**
     * Whether the license currently permits Pro features (valid or in grace).
     */
    private static function licensed(): bool {
        return class_exists(LicenseEnforcer::class)
            && LicenseEnforcer::getInstance()->canUseFeatures();
    }

    /**
     * Register the Pro deploy replacers and the sitemap/robots emitter onto the
     * Free deploy {@see Pipeline}. Free has already registered the Text replacer,
     * so the apply order stays text → media → link → video → data.
     */
    private static function register_pipeline(): void {
        Pipeline::register_replacer(new MediaDeployReplacer());
        Pipeline::register_replacer(new LinkDeployReplacer());
        Pipeline::register_replacer(new VideoDeployReplacer());

        Pipeline::register_extra_files(static function (array $page_urls): array {
            /** Filters whether sitemap.xml + robots.txt are generated. */
            if (!apply_filters('bs_generate_sitemaps', true)) {
                return [];
            }

            return SitemapGenerator::from_urls($page_urls);
        });
    }

    /**
     * Register the Pro REST controllers (hooked to `bs_register_rest_routes`).
     */
    public static function register_rest_routes(): void {
        MediaController::register();
        LinksController::register();
        VideosController::register();
        SitemapController::register();
    }

    /**
     * Register the license management admin page under the Free plugin's menu.
     */
    private static function register_license_page(): void {
        if (!class_exists(LicenseSettings::class)) {
            return;
        }

        add_action('init', static function (): void {
            try {
                $licensing = FluentLicensing::getInstance();
            } catch (\Throwable $e) {
                return;
            }

            $settings = new LicenseSettings();
            $settings->register($licensing, [
                'menu_title'   => BSP_LICENSE_MENU_TITLE,
                'page_title'   => BSP_LICENSE_PAGE_TITLE,
                'title'        => BSP_LICENSE_PAGE_TITLE,
                'purchase_url' => BSP_LICENSE_PURCHASE_URL,
                'account_url'  => BSP_LICENSE_ACCOUNT_URL,
                'plugin_name'  => 'Bricks Static Pro',
            ]);

            $settings->addPage([
                'type'        => BSP_LICENSE_MENU_TYPE,
                'page_title'  => BSP_LICENSE_PAGE_TITLE,
                'menu_title'  => BSP_LICENSE_MENU_TITLE,
                'parent_slug' => self::free_menu_slug(),
                'capability'  => 'manage_options',
            ]);
        });
    }

    /**
     * The Free plugin's top-level admin menu slug (parent for the license page).
     * Matches Menu::MENU_SLUG in the Free plugin and the wp.org plugin folder.
     */
    private static function free_menu_slug(): string {
        return 'bricks-static';
    }

    /**
     * Admin notice shown when the active Free plugin predates the required seams.
     */
    public static function notice_free_too_old(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html(sprintf(
            /* translators: %s: required Bricks Static version */
            __('Bricks Static Pro requires Bricks Static %s or newer. Please update the Bricks Static plugin.', 'bricks-static-pro'),
            BSP_MIN_FREE
        ));
        echo '</p></div>';
    }
}
