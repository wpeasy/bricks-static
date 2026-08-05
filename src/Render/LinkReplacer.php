<?php
/**
 * Per-destination link (href) swapping in rendered HTML.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Render;

defined('ABSPATH') || exit;

/**
 * Rewrites the `href` of <a> and <button> elements whose value matches a
 * replacement. Tag-scoped (never touches matching text in body content), so only
 * real link targets are swapped. (Iframe embeds are handled by VideoReplacer.)
 */
final class LinkReplacer {

    /**
     * Apply link swaps to HTML.
     *
     * @param string                $html  HTML.
     * @param array<string,string>  $swaps Original href => replacement href (keys decoded).
     * @return string
     */
    public static function apply(string $html, array $swaps): string {
        if (empty($swaps)) {
            return $html;
        }

        return (string) preg_replace_callback(
            '#<(?:a|button)\b[^>]*>#i',
            static function (array $m) use ($swaps): string {
                $tag = $m[0];
                // Decode entities (hrefs can carry &amp; in query strings) so they
                // match the stored, decoded swap key.
                $href = html_entity_decode(self::attr($tag, 'href'), ENT_QUOTES | ENT_HTML5);
                if ($href === '' || !isset($swaps[$href])) {
                    return $tag;
                }

                return self::set_attr($tag, 'href', $swaps[$href]);
            },
            $html
        );
    }

    /**
     * Read an attribute value (raw, as in the HTML).
     */
    private static function attr(string $tag, string $name): string {
        if (preg_match('#\b' . preg_quote($name, '#') . '\s*=\s*("([^"]*)"|\'([^\']*)\')#i', $tag, $m)) {
            return $m[2] !== '' ? $m[2] : ($m[3] ?? '');
        }

        return '';
    }

    /**
     * Set (replace, or add if missing) a double-quoted attribute.
     */
    private static function set_attr(string $tag, string $name, string $value): string {
        $value   = esc_attr($value);
        $pattern = '#\b' . preg_quote($name, '#') . '\s*=\s*("[^"]*"|\'[^\']*\')#i';

        if (preg_match($pattern, $tag)) {
            return (string) preg_replace($pattern, $name . '="' . $value . '"', $tag, 1);
        }

        return (string) preg_replace('#\s*/?>$#', ' ' . $name . '="' . $value . '"$0', $tag, 1);
    }
}
