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
 * Applies a destination's literal search/replace pairs to **visible text** and
 * **`<img>` image sources** only — never to other attributes, scripts, styles,
 * comments, or markup. Everything else stays byte-for-byte identical so the
 * static page is otherwise untouched.
 */
final class TextReplacer {

    /**
     * Image-source attributes that may be rewritten.
     */
    private const IMG_ATTRS = 'src|srcset|data-src|data-srcset|data-lazy-src|data-lazy-srcset';

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
            if ($i % 2 === 0) {
                // Text node.
                $parts[$i] = str_replace($searches, $replaces, $part);
            } elseif (preg_match('/^<img[\s>]/i', $part)) {
                // <img> tag — rewrite only image-source attribute values.
                $parts[$i] = self::replace_img_sources($part, $searches, $replaces);
            }
        }

        return implode('', $parts);
    }

    /**
     * Replace within an <img> tag's image-source attribute values only.
     *
     * @param string            $tag      The <img …> tag.
     * @param array<int,string> $searches Needles.
     * @param array<int,string> $replaces Replacements.
     */
    private static function replace_img_sources(string $tag, array $searches, array $replaces): string {
        $pattern = '/\b(' . self::IMG_ATTRS . ')\s*=\s*(["\'])(.*?)\2/i';

        return (string) preg_replace_callback(
            $pattern,
            static function (array $m) use ($searches, $replaces): string {
                return $m[1] . '=' . $m[2] . str_replace($searches, $replaces, $m[3]) . $m[2];
            },
            $tag
        );
    }
}
