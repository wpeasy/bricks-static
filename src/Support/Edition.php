<?php
/**
 * Edition + capability resolver — the single toggle point for the Free/Pro split.
 *
 * Every feature gate (PHP and JS) reads from here. Free defaults everything off;
 * the Pro addon flips the underlying `bs_license_edition` filter (via its
 * LicenseEnforcer) and, when licensed, the `bs_*` capability filters below.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.4
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Support;

defined('ABSPATH') || exit;

/**
 * Resolves the active edition and the capability map derived from it.
 */
final class Edition {

    /**
     * Sentinel "unlimited" used for the destination cap in the Pro edition.
     *
     * A large finite int (not PHP_INT_MAX) so it survives JSON → JS Number
     * without precision loss while still being effectively unbounded.
     */
    public const UNLIMITED = 9999;

    /**
     * Whether the Pro edition is active (licensed).
     */
    public static function is_pro(): bool {
        /**
         * Filters whether Pro features are active.
         *
         * @param bool $is_pro Defaults to the License edition being 'pro'.
         */
        return (bool) apply_filters('bs_is_pro', License::edition() === 'pro');
    }

    /**
     * The maximum number of destinations the current edition may use.
     */
    public static function max_destinations(): int {
        $default = self::is_pro() ? self::UNLIMITED : 1;

        /**
         * Filters the destination cap.
         *
         * @param int $max Default cap for the active edition.
         */
        return max(1, (int) apply_filters('bs_max_destinations', $default));
    }

    /**
     * The maximum number of HTML pages a full sync may render in the current
     * edition (Free is capped; Pro is effectively unlimited). Assets, sitemaps,
     * robots.txt and the favicon do not count toward this.
     */
    public static function max_pages(): int {
        $default = self::is_pro() ? self::UNLIMITED : 10;

        /**
         * Filters the per-run page cap.
         *
         * @param int $max Default cap for the active edition.
         */
        return max(1, (int) apply_filters('bs_max_pages', $default));
    }

    /**
     * Whether gzip pre-compression (.gz siblings + serving rules) is enabled.
     *
     * Defaults to the Pro edition; Pro adds an explicit `__return_true` filter
     * when licensed. Free always ships plain, uncompressed files.
     */
    public static function gzip_enabled(): bool {
        /** Filters whether gzip pre-compression runs. */
        return (bool) apply_filters('bs_gzip_enabled', self::is_pro());
    }

    /**
     * The canonical capability map — the one payload shipped to JS and
     * consulted across PHP. All flags derive from the edition so there is a
     * single source of truth.
     *
     * @return array{
     *     edition:string,
     *     maxDestinations:int,
     *     advancedReplacements:bool,
     *     gzip:bool,
     *     sitemap:bool,
     *     prune:bool,
     *     licenseValid:bool
     * }
     */
    public static function capabilities(): array {
        $pro = self::is_pro();

        return [
            'edition'              => $pro ? 'pro' : 'free',
            'maxDestinations'      => self::max_destinations(),
            'maxPages'             => self::max_pages(),
            'advancedReplacements' => $pro,
            'gzip'                 => self::gzip_enabled(),
            'sitemap'              => $pro,
            'prune'                => $pro,
            'licenseValid'         => $pro,
            // free | unlicensed | valid | grace | expired — drives upgrade vs renew copy.
            'licenseState'         => (string) License::status()['state'],
            // The Pro addon's version when it is installed (any license state), else ''.
            'proVersion'           => (string) apply_filters('bs_pro_version', ''),
        ];
    }
}
