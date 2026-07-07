<?php
/**
 * Strips WordPress head cruft from captured static HTML and rebrands the
 * generator meta.
 *
 * @package WPEasy\BricksStatic
 * @since   1.0.2
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Render;

defined('ABSPATH') || exit;

/**
 * Operates on the full rendered document (a string), so it runs deterministically
 * in the render pipeline — unlike render-time `remove_action`/output-buffering,
 * which depends on how/whether the theme reaches `wp_head` during the loopback.
 *
 * Removes ONLY WordPress-infrastructure `<link>`s that are redundant or broken on
 * a static host (feeds, oEmbed, the REST API, RSD/XML-RPC, wlwmanifest, the WP
 * shortlink), and rebrands the generator meta. Each pattern keys off a
 * WP-specific signal so SEO/social markup — robots, canonical, Open Graph,
 * Twitter cards, hreflang, preconnect, etc. — is left untouched.
 */
final class HeadCleaner {

    /**
     * `<link>` removal patterns, each matched by a WordPress-only signal. The
     * tag is matched whole (single tag — `[^>]*` never crosses `>`), with any
     * trailing line whitespace, so removal leaves no blank lines behind.
     *
     * @var string[]
     */
    private const LINK_PATTERNS = [
        // RSS/Atom feed links (site feed + per-page comment feed) — no /feed/ on static.
        '#[ \t]*<link\b[^>]*application/(?:rss|atom)\+xml[^>]*>[ \t]*\R?#i',
        // oEmbed discovery links (application/json+oembed, text/xml+oembed).
        '#[ \t]*<link\b[^>]*\+oembed[^>]*>[ \t]*\R?#i',
        // REST API links: rel="https://api.w.org/" and the rel="alternate" JSON
        // representation — both reference wp-json, which is gone on static.
        '#[ \t]*<link\b[^>]*(?:api\.w\.org|wp-json)[^>]*>[ \t]*\R?#i',
        // RSD / XML-RPC endpoint (rel="EditURI").
        '#[ \t]*<link\b[^>]*application/rsd\+xml[^>]*>[ \t]*\R?#i',
        // Windows Live Writer manifest.
        '#[ \t]*<link\b[^>]*wlwmanifest[^>]*>[ \t]*\R?#i',
        // WP shortlink (rel="shortlink") — points at a /?p=ID URL that 404s on static.
        '#[ \t]*<link\b[^>]*\brel=([\'"])shortlink\1[^>]*>[ \t]*\R?#i',
    ];

    /**
     * Clean the captured HTML.
     *
     * @param string $html The rendered document.
     * @return string The cleaned document.
     */
    public static function clean(string $html): string {
        /** Filters whether redundant WordPress head links are stripped from the static output. */
        if (!apply_filters('bs_clean_head', true)) {
            return $html;
        }

        foreach (self::LINK_PATTERNS as $pattern) {
            $html = (string) preg_replace($pattern, '', $html);
        }

        // Strip EVERY generator meta (WordPress, theme, other plugins), any
        // attribute order, then inject a single one of our own. (The robots meta
        // and other SEO/social metas are left alone — only `name="generator"`
        // matches here.)
        $html = (string) preg_replace('#[ \t]*<meta\b[^>]*\bname=([\'"])generator\1[^>]*>[ \t]*\R?#i', '', $html);

        $tag = '<meta name="generator" content="' . esc_attr('Bricks Sync ' . BS_VERSION . ' by BRXProd') . '" />';
        if (stripos($html, '</head>') !== false) {
            return (string) preg_replace('#</head>#i', $tag . "\n</head>", $html, 1);
        }

        return $tag . $html;
    }
}
