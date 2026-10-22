<?php
/**
 * Cloudflare `_headers` / `_redirects` builder.
 *
 * @package WPEasy\BricksStatic
 * @since   2.1.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Deploy;

use WPEasy\BricksStatic\Sync\HtaccessBuilder;

defined('ABSPATH') || exit;

/**
 * Cloudflare's equivalent of the `.htaccess` we upload to Apache: the same
 * cache policy, expressed as `_headers` rules. Cloudflare compresses
 * (gzip/brotli) at the edge itself, so there are no `.gz` rewrite rules, and it
 * resolves `about/` → `about/index.html` natively (`html_handling`).
 *
 * The rules are sent in the publish metadata (`assets.config._headers`), not
 * uploaded as a file — that is what wrangler does too.
 *
 * Limits (Cloudflare): 100 header rules, 2,000 characters per header line;
 * 2,000 static + 100 dynamic redirects. We emit one rule per long-cache
 * extension (~20), far inside them.
 */
final class CloudflareHeaders {

    /**
     * Long-cache policy for assets — identical to the .htaccess/nginx rules.
     */
    private const IMMUTABLE = 'public, max-age=31536000, immutable';

    /**
     * The `_headers` file contents.
     *
     * HTML needs no rule: Cloudflare already serves assets with
     * `public, max-age=0, must-revalidate`, which is our HTML policy. (A `/*`
     * catch-all would ALSO match the assets, and Cloudflare joins duplicate
     * headers with a comma — so it must not be added.)
     */
    public static function headers(): string {
        $rules = [];
        foreach (HtaccessBuilder::asset_extensions() as $ext) {
            $rules[] = "/*.{$ext}\n  Cache-Control: " . self::IMMUTABLE;
        }
        $text = implode("\n", $rules) . "\n";

        /**
         * Filters the `_headers` rules sent to a Cloudflare destination.
         *
         * @param string $text `_headers` file contents.
         */
        return (string) apply_filters('bs_cloudflare_headers', $text);
    }

    /**
     * The `_redirects` file contents. Empty by default — the export has no
     * redirects of its own — but filterable for sites that need some.
     */
    public static function redirects(): string {
        /**
         * Filters the `_redirects` rules sent to a Cloudflare destination
         * (one `/from /to [status]` per line).
         *
         * @param string $text `_redirects` file contents.
         */
        return (string) apply_filters('bs_cloudflare_redirects', '');
    }
}
