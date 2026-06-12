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
     * Enqueue the base framework CSS on our admin page only.
     *
     * @param string $hook The current admin page hook suffix.
     */
    public static function enqueue_assets(string $hook): void {
        if ($hook !== self::$page_hook) {
            return;
        }

        wp_enqueue_style(
            'bs-framework',
            BS_PLUGIN_URL . 'assets/css/bs-framework.css',
            [],
            BS_VERSION
        );
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
