<?php
/**
 * Per-page media swapping in rendered HTML.
 *
 * @package WPEasy\BricksStatic
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Render;

defined('ABSPATH') || exit;

/**
 * Swaps media in a rendered document given a prepared swap table (already filtered
 * to the page being processed). For <img> elements whose source matches a
 * replacement — including LAZY images, where Bricks keeps the real URL in
 * data-src/data-srcset and a placeholder in src — it points the image at the
 * replacement and regenerates the WHOLE srcset/data-srcset from the new image's
 * own variants, so the swap looks right at every screen width and never leaves (or
 * collapses) the original's responsive set. Intrinsic width/height are kept in
 * step too. Any remaining references (CSS backgrounds, video posters) are swapped
 * literally.
 */
final class MediaReplacer {

    /**
     * Apply media swaps to HTML.
     *
     * @param string                                                              $html  HTML.
     * @param array<string,array{to:string,srcset:string,width?:int,height?:int}> $swaps Original URL => {to, srcset, width, height}.
     * @return string
     */
    public static function apply(string $html, array $swaps): string {
        if (empty($swaps)) {
            return $html;
        }

        // Tag-aware <img> rewrite (handles lazy images). Match on src OR data-src;
        // rewrite whichever points at a swapped original, regenerate the entire
        // srcset/data-srcset from the new image (or drop it), and align width/height.
        $html = (string) preg_replace_callback(
            '#<img\b[^>]*>#i',
            static function (array $m) use ($swaps): string {
                $tag  = $m[0];
                $src  = self::attr($tag, 'src');
                $data = self::attr($tag, 'data-src');

                $swap = $swaps[$src] ?? $swaps[$data] ?? null;
                if ($swap === null) {
                    return $tag;
                }

                if ($src !== '' && isset($swaps[$src])) {
                    $tag = self::set_attr($tag, 'src', $swap['to']);
                }
                if ($data !== '' && isset($swaps[$data])) {
                    $tag = self::set_attr($tag, 'data-src', $swap['to']);
                }

                // Regenerate (or drop) every responsive set — real and lazy.
                foreach (['srcset', 'data-srcset'] as $attr) {
                    if (self::attr($tag, $attr) === '') {
                        continue;
                    }
                    $tag = $swap['srcset'] !== ''
                        ? self::set_attr($tag, $attr, $swap['srcset'])
                        : self::remove_attr($tag, $attr);
                }

                // Keep intrinsic dimensions in step so a cross-aspect swap isn't
                // stretched by width/height attributes (only when known + present).
                if ((int) ($swap['width'] ?? 0) > 0 && self::attr($tag, 'width') !== '') {
                    $tag = self::set_attr($tag, 'width', (string) (int) $swap['width']);
                }
                if ((int) ($swap['height'] ?? 0) > 0 && self::attr($tag, 'height') !== '') {
                    $tag = self::set_attr($tag, 'height', (string) (int) $swap['height']);
                }

                return $tag;
            },
            $html
        );

        // Literal swap for any non-<img> occurrences (CSS backgrounds, posters).
        // Safe now: matched <img> sets already hold the new URLs, so the only
        // remaining originals are single-URL references that can't collapse.
        $froms = array_keys($swaps);
        $tos   = array_map(static fn(array $s): string => $s['to'], $swaps);

        return str_replace($froms, $tos, $html);
    }

    /**
     * Read an attribute value (raw, as in the HTML). The negative lookbehind stops
     * `src` matching `data-src` (and `srcset` matching `data-srcset`).
     */
    private static function attr(string $tag, string $name): string {
        if (preg_match('#(?<![\w-])' . preg_quote($name, '#') . '\s*=\s*("([^"]*)"|\'([^\']*)\')#i', $tag, $m)) {
            return $m[2] !== '' ? $m[2] : ($m[3] ?? '');
        }

        return '';
    }

    /**
     * Set (replace, or add if missing) a double-quoted attribute.
     */
    private static function set_attr(string $tag, string $name, string $value): string {
        $value   = esc_attr($value);
        $pattern = '#(?<![\w-])' . preg_quote($name, '#') . '\s*=\s*("[^"]*"|\'[^\']*\')#i';

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
        return (string) preg_replace('#\s+(?<![\w-])' . preg_quote($name, '#') . '\s*=\s*("[^"]*"|\'[^\']*\')#i', '', $tag);
    }
}
