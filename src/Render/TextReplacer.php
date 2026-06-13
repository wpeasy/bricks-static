<?php
/**
 * Per-destination literal text replacement (scoped to text + image sources).
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Render;

defined('ABSPATH') || exit;

/**
 * Applies a destination's literal search/replace pairs to **visible text content
 * only** — the text between HTML tags. Tags, attributes, scripts, styles, and
 * comments are left byte-for-byte identical, so the static page is otherwise
 * untouched. (Media URLs are handled separately by the media replacer.)
 *
 * A replacement value may be plain text or, for "rich" replacements, inline HTML
 * — both are substituted verbatim into the matched text run.
 */
final class TextReplacer {

    /**
     * Apply replacements to an HTML document.
     *
     * @param string             $html     HTML.
     * @param array<int,string>  $searches Literal needles.
     * @param array<int,string>  $replaces Replacements (parallel to $searches).
     * @return string Transformed HTML.
     */
    public static function apply(string $html, array $searches, array $replaces): string {
        if (empty($searches)) {
            return $html;
        }

        // Split into [text, token, text, token, …] where tokens are tags,
        // scripts, styles, and comments (left untouched, except <img> sources).
        $parts = preg_split(
            '/(<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>|<!--.*?-->|<[^>]+>)/is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if ($parts === false) {
            return $html;
        }

        foreach ($parts as $i => $part) {
            // Even indices are text runs (between tags); odd indices are the
            // captured tags/scripts/styles/comments, left untouched.
            if ($i % 2 === 0) {
                $parts[$i] = str_replace($searches, $replaces, $part);
            }
        }

        return implode('', $parts);
    }
}
