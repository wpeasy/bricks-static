<?php
/**
 * License/edition source of truth (pluggable contract).
 *
 * Free ships with no licensing logic of its own — it always reports the free
 * edition. The Pro addon hooks the `bs_license_edition` / `bs_license_status`
 * filters to flip the edition based on a valid FluentCart license. Keeping the
 * contract here (not in Pro) means all gating logic lives in Free and the
 * provider is swappable: there is exactly one place the rest of the plugin asks
 * "are we licensed?".
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.4
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Support;

defined('ABSPATH') || exit;

/**
 * The plugin's license/edition contract.
 */
final class License {

    /**
     * The active edition: 'free' or 'pro'.
     *
     * Pro returns 'pro' only while its license is valid (or in grace); on
     * expiry it returns 'free' again so paid features hard-gate off.
     */
    public static function edition(): string {
        /**
         * Filters the active edition.
         *
         * @param string $edition Either 'free' or 'pro'. Defaults to 'free'.
         */
        $edition = (string) apply_filters('bs_license_edition', 'free');

        return $edition === 'pro' ? 'pro' : 'free';
    }

    /**
     * The full license status payload (for admin display / diagnostics).
     *
     * @return array{valid:bool,state:string,expires:string}
     */
    public static function status(): array {
        $default = [
            'valid'   => false,
            'state'   => 'free', // free | valid | grace | expired | unlicensed
            'expires' => '',
        ];

        /**
         * Filters the license status payload.
         *
         * @param array $status Default (free) status.
         */
        $status = apply_filters('bs_license_status', $default);

        return is_array($status) ? array_merge($default, $status) : $default;
    }
}
