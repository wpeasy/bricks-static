<?php
/**
 * Per-destination video/embed swapping + embed-origin rewriting in rendered HTML.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Render;

defined('ABSPATH') || exit;

/**
 * For each <iframe> embed (YouTube/Vimeo/Maps/…):
 *  1. If its src matches a per-destination replacement, swap the whole src.
 *  2. Otherwise, rewrite any occurrence of the SOURCE origin embedded in the src
 *     — including the percent-encoded `origin=https%3A%2F%2Fhost` param common in
 *     YouTube embeds — to the DESTINATION origin, so the embed behaves on the new
 *     domain (the literal-URL rewriter never sees the percent-encoded form).
 */
final class VideoReplacer {

    /**
     * Apply video swaps + embed-origin fixes to HTML.
     *
     * @param string                $html      HTML.
     * @param array<string,string>  $swaps     Original iframe src => replacement (keys decoded).
     * @param string                $dest_base Destination base URL (for the origin fix; '' to skip).
     * @return string
     */
    public static function apply(string $html, array $swaps, string $dest_base = ''): string {
        if (empty($swaps) && $dest_base === '') {
            return $html;
        }

        return (string) preg_replace_callback(
            '#<(iframe|video|source)\b[^>]*>#i',
            static function (array $m) use ($swaps, $dest_base): string {
                $tag = $m[0];
                $raw = self::attr($tag, 'src');
                if ($raw === '') {
                    return $tag; // e.g. a <video> that uses <source> children.
                }
                // Decode entities so &amp;-laden query strings match the stored key.
                $src = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5);

                if (isset($swaps[$src])) {
                    return self::set_attr($tag, 'src', $swaps[$src]);
                }

                // Embed-origin fix applies to iframes only (local files have no origin).
                if (strtolower($m[1]) === 'iframe' && $dest_base !== '') {
                    $fixed = self::fix_origin($src, $dest_base);
                    if ($fixed !== $src) {
                        return self::set_attr($tag, 'src', $fixed);
                    }
                }

                return $tag;
            },
            $html
        );
    }

    /**
     * Rewrite the source origin (literal and percent-encoded) embedded in a URL to
     * the destination origin. Used for the YouTube `origin=` param et al.
     *
     * @param string $url       The iframe src (entity-decoded).
     * @param string $dest_base Destination base URL.
     */
    private static function fix_origin(string $url, string $dest_base): string {
        $parts = wp_parse_url($dest_base);
        if (empty($parts['host'])) {
            return $url;
        }
        $scheme    = $parts['scheme'] ?? 'https';
        $authority = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $origin    = $scheme . '://' . $authority;
        $enc       = rawurlencode($origin); // https%3A%2F%2Fhost

        $search  = [];
        $replace = [];
        foreach (UrlRewriter::source_hosts() as $host) {
            // Percent-encoded origin (?origin=https%3A%2F%2Fhost), upper & lower hex.
            $search[] = 'https%3A%2F%2F' . $host;
            $replace[] = $enc;
            $search[] = 'http%3A%2F%2F' . $host;
            $replace[] = $enc;
            $search[] = 'https%3a%2f%2f' . $host;
            $replace[] = $enc;
            $search[] = 'http%3a%2f%2f' . $host;
            $replace[] = $enc;
            // Literal, just in case it appears unencoded in the query.
            $search[] = 'https://' . $host;
            $replace[] = $origin;
            $search[] = 'http://' . $host;
            $replace[] = $origin;
        }

        return str_replace($search, $replace, $url);
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
