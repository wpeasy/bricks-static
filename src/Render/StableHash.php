<?php
/**
 * Render-stable content hashing.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Render;

defined('ABSPATH') || exit;

/**
 * Hashes rendered output for the manifest in a way that ignores per-render noise
 * — chiefly Bricks' randomly-generated element/form IDs, which change on every
 * render even when the content is identical. Without this, those pages would
 * re-upload on every sync and flip other destinations to "out of date".
 *
 * The DEPLOYED file is never altered (it keeps its real IDs); only the hash used
 * for delta/in-sync comparisons is computed from a normalised copy.
 */
final class StableHash {

    /**
     * Bricks per-render id patterns → stable placeholders. Applied for hashing
     * only. Filterable so other builders/themes can add their own noise patterns.
     *
     * @return array<string,string>
     */
    private static function patterns(): array {
        // Bricks generates these as Helpers::generate_random_id() — a fresh
        // 6-char lowercase-hex hash on EVERY render. They appear as the element
        // id (brxe-…), form field ids, and a fixed set of data-* attributes.
        //
        // NB: data-id is deliberately NOT here — Bricks reuses it for real
        // attachment IDs on gallery images (a meaningful number), so normalising
        // it would hide genuine image changes. (A rare video wrapper's random
        // data-id is the only false negative, and it's worth it for safety.)
        $map = [
            '/\bbrxe-[0-9a-f]{6,}\b/i'       => 'brxe-X',
            '/\bform-field-[a-z0-9]{5,}\b/i' => 'form-field-X',
            '/(data-(?:script-id|element-id|brx-loop-start|query-element-id|interaction-id|pagination-id)=")[0-9a-f]{4,}"/i' => '$1X"',
        ];

        /**
         * Filters the regex→replacement map used to normalise rendered HTML
         * before hashing (delta/in-sync stability).
         *
         * @param array<string,string> $map Pattern => replacement.
         */
        return (array) apply_filters('bs_stable_hash_patterns', $map);
    }

    /**
     * Hash a file: HTML is normalised first, everything else is hashed as-is.
     *
     * @param string $path Absolute file path.
     */
    public static function of_file(string $path): string {
        if (self::is_html($path)) {
            $content = @file_get_contents($path);
            if ($content !== false) {
                return self::of_html($content);
            }
        }

        return (string) md5_file($path);
    }

    /**
     * Hash an HTML string with per-render noise normalised away.
     *
     * @param string $html HTML.
     */
    public static function of_html(string $html): string {
        $map        = self::patterns();
        $normalised = preg_replace(array_keys($map), array_values($map), $html);

        return md5(is_string($normalised) ? $normalised : $html);
    }

    /**
     * Whether a path is an HTML document.
     *
     * @param string $path File path.
     */
    private static function is_html(string $path): bool {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $ext === 'html' || $ext === 'htm';
    }
}
