<?php
/**
 * Pulls the searchable text out of an exported page.
 *
 * @package WPEasy\ShipStatic
 * @since   2.2.0
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Search;

defined('ABSPATH') || exit;

/**
 * Reads a page's title, headings and main text for the site-search index.
 *
 * Only the page's main content counts: the first of #brx-content (Bricks),
 * <main> or [role=main] — falling back to <body> — with scripts, styles,
 * navigation, headers, footers, forms and hidden text removed, so every page
 * doesn't match on its menu and footer.
 */
final class TextExtractor {

    /** Elements never read as text. */
    private const DROP_TAGS = ['script', 'style', 'noscript', 'template', 'svg', 'nav', 'header', 'footer', 'form', 'iframe', 'select', 'button'];

    /** Max headings kept per page. */
    private const MAX_HEADINGS = 20;

    /**
     * Extract a page's searchable parts.
     *
     * @param string $html Final page HTML (after replacements).
     * @return array{t:string,h:array<int,string>,x:string}|null Null when the page asks not to be indexed.
     */
    public static function extract(string $html): ?array {
        if ($html === '') {
            return null;
        }

        $doc      = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // The XML prolog makes DOMDocument read the page as UTF-8.
        $loaded = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return null;
        }

        $xpath = new \DOMXPath($doc);

        // <meta name="robots" content="…noindex…"> — the page opted out of search.
        foreach ($xpath->query('//meta[@name]') ?: [] as $meta) {
            if ($meta instanceof \DOMElement
                && strtolower($meta->getAttribute('name')) === 'robots'
                && stripos($meta->getAttribute('content'), 'noindex') !== false) {
                return null;
            }
        }

        $title_node = $xpath->query('//title')->item(0);
        $title      = $title_node !== null ? self::clean($title_node->textContent) : '';

        $root = self::content_root($xpath);
        if ($root === null) {
            return ['t' => self::strip_site_name($title), 'h' => [], 'x' => ''];
        }

        // Drop the parts that aren't page content.
        $drop = [];
        foreach (self::DROP_TAGS as $tag) {
            foreach ($xpath->query('.//' . $tag, $root) ?: [] as $node) {
                $drop[] = $node;
            }
        }
        $hidden = './/*[@aria-hidden="true" or @hidden or contains(concat(" ", normalize-space(@class), " "), " screen-reader-text ")]';
        foreach ($xpath->query($hidden, $root) ?: [] as $node) {
            $drop[] = $node;
        }
        foreach ($drop as $node) {
            if ($node->parentNode !== null) {
                $node->parentNode->removeChild($node);
            }
        }

        $headings = [];
        foreach ($xpath->query('.//h1|.//h2|.//h3', $root) ?: [] as $node) {
            $text = self::clean($node->textContent);
            if ($text !== '' && !in_array($text, $headings, true)) {
                $headings[] = $text;
                if (count($headings) >= self::MAX_HEADINGS) {
                    break;
                }
            }
        }

        // Block-level boundaries become spaces so "Heading" + "Text" don't run
        // together as "HeadingText".
        $text = self::clean(self::text_with_breaks($root));

        /**
         * Filters the max characters of main text kept per page.
         *
         * @param int $limit Default 3000.
         */
        $limit = max(200, (int) apply_filters('shipstatic_search_text_limit', 3000));
        if (mb_strlen($text) > $limit) {
            $text = rtrim(mb_substr($text, 0, $limit));
        }

        return ['t' => self::strip_site_name($title), 'h' => $headings, 'x' => $text];
    }

    /**
     * The page's main content element.
     *
     * @param \DOMXPath $xpath Page XPath.
     */
    private static function content_root(\DOMXPath $xpath): ?\DOMNode {
        foreach (['//*[@id="brx-content"]', '//main', '//*[@role="main"]', '//body'] as $query) {
            $node = $xpath->query($query)->item(0);
            if ($node !== null) {
                return $node;
            }
        }

        return null;
    }

    /**
     * An element's text with a space between every text node.
     *
     * @param \DOMNode $node Root.
     */
    private static function text_with_breaks(\DOMNode $node): string {
        $parts = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $parts[] = $child->wholeText;
            } elseif ($child instanceof \DOMElement) {
                $parts[] = self::text_with_breaks($child);
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Decode entities and collapse whitespace.
     *
     * @param string $text Raw text.
     */
    private static function clean(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * "About us – My Site" → "About us". Only the WordPress default pattern
     * (separator + the site name at the end) is removed; any other title is
     * kept as written.
     *
     * @param string $title Page <title>.
     */
    private static function strip_site_name(string $title): string {
        $site = self::clean((string) get_bloginfo('name'));
        if ($site === '') {
            return $title;
        }
        $stripped = (string) preg_replace('/\s+[–—\-|·•]\s+' . preg_quote($site, '/') . '$/u', '', $title);

        return $stripped !== '' ? $stripped : $title;
    }
}
