<?php
/**
 * Per-destination media swapping in rendered HTML.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Render;

defined('ABSPATH') || exit;

/**
 * Swaps media in a rendered document. For <img> elements whose src matches a
 * replacement, it rewrites BOTH the src and the srcset — replacing the original
 * image's responsive variants with the new image's own variants, so the swapped
 * image looks right at every screen width. Any remaining references to the
 * original URL (e.g. CSS backgrounds, video posters) are swapped literally.
 */
final class MediaReplacer {

    /**
     * Apply media swaps to HTML.
     *
     * @param string                                              $html  HTML.
     * @param array<string,array{to:string,srcset:string}>        $swaps Original URL => {to, srcset}.
     * @return string
     */
    public static function apply(string $html, array $swaps): string {
        if (empty($swaps)) {
            return $html;
        }

        // Tag-aware <img> rewrite: when the src is a swapped original, replace
        // src + srcset (the new image's variants, or drop srcset if none).
        $html = (string) preg_replace_callback(
            '#<img\b[^>]*>#i',
            static function (array $m) use ($swaps): string {
                $tag = $m[0];
                $src = self::attr($tag, 'src');
                if (!isset($swaps[$src])) {
                    return $tag;
                }
                $swap = $swaps[$src];
                $tag  = self::set_attr($tag, 'src', $swap['to']);
                $tag  = $swap['srcset'] !== ''
                    ? self::set_attr($tag, 'srcset', $swap['srcset'])
                    : self::remove_attr($tag, 'srcset');

                return $tag;
            },
            $html
        );

        // Literal swap for any other occurrences (backgrounds, posters, etc.).
        $froms = array_keys($swaps);
        $tos   = array_map(static fn(array $s): string => $s['to'], $swaps);

        return str_replace($froms, $tos, $html);
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

        // Insert before the closing > (or />).
        return (string) preg_replace('#\s*/?>$#', ' ' . $name . '="' . $value . '"$0', $tag, 1);
    }

    /**
     * Remove an attribute.
     */
    private static function remove_attr(string $tag, string $name): string {
        return (string) preg_replace('#\s+\b' . preg_quote($name, '#') . '\s*=\s*("[^"]*"|\'[^\']*\')#i', '', $tag);
    }
}
