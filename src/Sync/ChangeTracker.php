<?php
/**
 * Tracks whether the site content has changed since the last render, so the
 * dashboard can flag destinations out of sync and prompt a re-render.
 *
 * @package WPEasy\BricksStatic
 * @since   1.0.2
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Sync;

use WPEasy\BricksStatic\Discovery\UrlCollector;

defined('ABSPATH') || exit;

/**
 * The render manifest is only rebuilt by a Check/Sync run — nothing renders on
 * post save. So after content changes the export is stale, yet (without this) the
 * "In sync" dot stays green and the pages list shows the old split. This watches
 * public-content edits and raises a "dirty" flag; the flag is cleared whenever a
 * full render finalises. {@see Runner::in_sync()} treats a dirty flag as out of
 * sync, and the dashboard offers "Process" (re-render) instead of "View list".
 */
final class ChangeTracker {

    /**
     * Option: set when public content has changed since the last full render.
     */
    public const DIRTY_OPTION = 'bs_content_dirty';

    /**
     * Option: the discovery mode in force at the last full render. A mode change
     * also makes the render (and the included/excluded split) stale.
     */
    public const MODE_OPTION = 'bs_render_mode';

    /**
     * Register hooks.
     */
    public static function init(): void {
        add_action('save_post', [self::class, 'on_save_post'], 10, 2);
        add_action('deleted_post', [self::class, 'on_post_id_change']);
        add_action('trashed_post', [self::class, 'on_post_id_change']);
        add_action('untrashed_post', [self::class, 'on_post_id_change']);
        add_action('transition_post_status', [self::class, 'on_transition'], 10, 3);
        // Bricks stores page content in `_bricks_*` post meta and may save it
        // without a full post update, so watch those meta writes too.
        add_action('updated_post_meta', [self::class, 'on_meta'], 10, 3);
        add_action('added_post_meta', [self::class, 'on_meta'], 10, 3);
        // A nav-menu edit changes the link graph (so the linked crawl reaches
        // different pages) AND the menu markup baked into every exported page —
        // but it saves `nav_menu_item` posts, not a public type, so the hooks
        // above miss it. Flag dirty whenever a menu is saved/reordered.
        add_action('wp_update_nav_menu', [self::class, 'mark_dirty']);
    }

    /**
     * @param int      $post_id Post id.
     * @param \WP_Post $post    Post object.
     */
    public static function on_save_post($post_id, $post): void {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        if (!($post instanceof \WP_Post) || $post->post_status === 'auto-draft') {
            return;
        }
        if (self::is_public_type((string) $post->post_type)) {
            self::mark_dirty();
        }
    }

    /**
     * @param string   $new  New status.
     * @param string   $old  Old status.
     * @param \WP_Post $post Post object.
     */
    public static function on_transition($new, $old, $post): void {
        if ($new === $old || !($post instanceof \WP_Post)) {
            return;
        }
        if (self::is_public_type((string) $post->post_type)) {
            self::mark_dirty();
        }
    }

    /**
     * Delete/trash/untrash, which pass only the post id.
     *
     * @param int $post_id Post id.
     */
    public static function on_post_id_change($post_id): void {
        $type = (string) get_post_type((int) $post_id);
        if ($type !== '' && self::is_public_type($type)) {
            self::mark_dirty();
        }
    }

    /**
     * @param int    $meta_id  Meta row id (unused).
     * @param int    $post_id  Post id.
     * @param string $meta_key Meta key.
     */
    public static function on_meta($meta_id, $post_id, $meta_key): void {
        unset($meta_id);
        if (strpos((string) $meta_key, '_bricks') !== 0) {
            return; // Only Bricks builder content meta.
        }
        $type = (string) get_post_type((int) $post_id);
        if ($type !== '' && self::is_public_type($type)) {
            self::mark_dirty();
        }
    }

    /**
     * Whether a post type is one we'd export.
     *
     * @param string $type Post type.
     */
    private static function is_public_type(string $type): bool {
        return in_array($type, UrlCollector::public_post_types(), true);
    }

    /**
     * Flag the render as stale (content changed since it was built).
     */
    public static function mark_dirty(): void {
        update_option(self::DIRTY_OPTION, 1, false);
    }

    /**
     * Record that a full render just finalised: clear the dirty flag and store the
     * discovery mode it was built under.
     *
     * @param string $mode Discovery mode used for the render.
     */
    public static function mark_rendered(string $mode): void {
        update_option(self::DIRTY_OPTION, 0, false);
        update_option(self::MODE_OPTION, $mode, false);
    }

    /**
     * Clear all tracking (e.g. on Reset).
     */
    public static function forget(): void {
        delete_option(self::DIRTY_OPTION);
        delete_option(self::MODE_OPTION);
    }

    /**
     * Whether content has changed since the last full render.
     */
    public static function is_dirty(): bool {
        return (bool) get_option(self::DIRTY_OPTION, false);
    }

    /**
     * The discovery mode the current render was built under ('' if never).
     */
    public static function rendered_mode(): string {
        return (string) get_option(self::MODE_OPTION, '');
    }
}
