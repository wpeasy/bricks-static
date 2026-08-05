<?php
/**
 * Collects the link targets (href) referenced by the rendered site.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Media;

use WPEasy\BricksStatic\Sync\Manifest;

defined('ABSPATH') || exit;

/**
 * Scans the last render's cached HTML for the links each page uses (<a> and
 * <button> hrefs) — so the dashboard can list every link target with its label
 * and the page(s) it appears on, ready for per-destination URL swapping.
 */
final class LinkCollector {

    /**
     * Build the link index from the current render.
     *
     * @return array<int,array{url:string,text:string,pages:array<int,string>}>
     */
    public static function collect(): array {
        $render = Manifest::load(Manifest::RENDER_OPTION);

        // url => [text, pages{}]
        $index = [];

        foreach ($render as $relative => $meta) {
            if (substr(strtolower($relative), -5) !== '.html' || !is_file($meta['src'])) {
                continue;
            }
            $html = (string) file_get_contents($meta['src']);
            $page = self::page_path($relative);

            foreach (self::links_in($html) as $item) {
                $url = $item['url'];
                if (!isset($index[$url])) {
                    $index[$url] = ['text' => $item['text'], 'pages' => []];
                }
                if ($item['text'] !== '' && $index[$url]['text'] === '') {
                    $index[$url]['text'] = $item['text'];
                }
                $index[$url]['pages'][$page] = true;
            }
        }

        $out = [];
        foreach ($index as $url => $info) {
            $out[] = ['url' => $url, 'text' => $info['text'], 'pages' => array_keys($info['pages'])];
        }

        usort($out, static fn(array $a, array $b): int => strcasecmp($a['url'], $b['url']));

        return $out;
    }

    /**
     * Extract link references (a/button hrefs) from one HTML document.
     *
     * @param string $html HTML.
     * @return array<int,array{url:string,text:string}>
     */
    private static function links_in(string $html): array {
        $found = [];

        // <a ...>label</a> — capture href + the visible label.
        if (preg_match_all('#<a\b([^>]*)>(.*?)</a>#is', $html, $anchors, PREG_SET_ORDER)) {
            foreach ($anchors as $set) {
                $href = self::attr('<a' . $set[1] . '>', 'href');
                if (!self::is_linkable($href)) {
                    continue;
                }
                $found[] = ['url' => $href, 'text' => self::text($set[2])];
            }
        }

        // <button ... href="…"> — Bricks buttons usually render as <a>, but cover
        // a button that carries an explicit href too.
        if (preg_match_all('#<button\b[^>]*>#i', $html, $buttons)) {
            foreach ($buttons[0] as $tag) {
                $href = self::attr($tag, 'href');
                if (!self::is_linkable($href)) {
                    continue;
                }
                $found[] = ['url' => $href, 'text' => ''];
            }
        }

        // (iframe embeds are handled by the Videos panel — see VideoCollector.)

        return $found;
    }

    /**
     * Whether an href is a real, swappable link target (not an anchor, protocol
     * handler, JS, or data URI).
     *
     * @param string $href Raw href.
     */
    private static function is_linkable(string $href): bool {
        $href = trim($href);
        if ($href === '' || $href === '#') {
            return false;
        }
        foreach (['#', 'mailto:', 'tel:', 'javascript:', 'data:', 'sms:', 'about:'] as $skip) {
            if (stripos($href, $skip) === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Visible label of a link: tags stripped, entities decoded, whitespace
     * collapsed, trimmed to a reasonable length.
     *
     * @param string $inner Inner HTML of the anchor.
     */
    private static function text(string $inner): string {
        $text = trim((string) preg_replace('/\s+/', ' ', wp_strip_all_tags($inner)));
        $text = html_entity_decode($text, ENT_QUOTES);

        return mb_strlen($text) > 80 ? mb_substr($text, 0, 79) . '…' : $text;
    }

    /**
     * Read an attribute value from a tag string.
     *
     * @param string $tag  Tag markup.
     * @param string $name Attribute name.
     */
    private static function attr(string $tag, string $name): string {
        if (preg_match('#\b' . preg_quote($name, '#') . '\s*=\s*("([^"]*)"|\'([^\']*)\')#i', $tag, $m)) {
            return html_entity_decode($m[2] !== '' ? $m[2] : ($m[3] ?? ''), ENT_QUOTES);
        }

        return '';
    }

    /**
     * Turn a cached relative file path into a site page path for display.
     *
     * @param string $relative e.g. "about/index.html".
     */
    private static function page_path(string $relative): string {
        $path = '/' . preg_replace('#/?index\.html$#i', '/', $relative);

        return $path === '/' ? '/' : rtrim($path, '/') . '/';
    }
}
