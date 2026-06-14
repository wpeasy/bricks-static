<?php
/**
 * Per-destination data-* attribute VALUE swapping in rendered HTML.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Render;

defined('ABSPATH') || exit;

/**
 * Rewrites the VALUE of `data-*` attributes whose (name, value) matches a
 * replacement — the attribute name is never changed. Matching is entity-aware
 * (the value in the HTML may carry &amp; etc.) and scoped to the attribute
 * syntax, so body text is never touched.
 */
final class DataAttrReplacer {

    /**
     * Apply data-attribute value swaps to HTML.
     *
     * @param string                                          $html  HTML.
     * @param array<int,array{attr:string,from:string,to:string}> $pairs Replacements.
     * @return string
     */
    public static function apply(string $html, array $pairs): string {
        if (empty($pairs)) {
            return $html;
        }

        // Group by attribute name: map[attr][fromValue] = toValue.
        $map = [];
        foreach ($pairs as $p) {
            $attr = (string) ($p['attr'] ?? '');
            if ($attr === '') {
                continue;
            }
            $map[$attr][(string) ($p['from'] ?? '')] = (string) ($p['to'] ?? '');
        }
        if (empty($map)) {
            return $html;
        }

        // Operate on HTML tags ONLY — never body text, scripts, styles or comments
        // — so a literal data-x="…" in content/JS is left alone.
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
            // Odd indices are the captured tokens; rewrite only real element tags.
            if ($i % 2 === 1 && isset($part[0]) && $part[0] === '<'
                && stripos($part, '<script') !== 0 && stripos($part, '<style') !== 0 && strpos($part, '<!--') !== 0
            ) {
                $parts[$i] = self::rewrite_tag($part, $map);
            }
        }

        return implode('', $parts);
    }

    /**
     * Rewrite matching data-* attribute values within a single element tag.
     *
     * @param string                                  $tag One HTML tag.
     * @param array<string,array<string,string>>      $map attr => [fromValue => toValue].
     */
    private static function rewrite_tag(string $tag, array $map): string {
        foreach ($map as $attr => $from_to) {
            $pattern = '#\bdata-' . preg_quote($attr, '#') . '\s*=\s*("([^"]*)"|\'([^\']*)\')#i';
            $tag     = (string) preg_replace_callback(
                $pattern,
                static function (array $m) use ($attr, $from_to): string {
                    $raw   = $m[2] !== '' ? $m[2] : ($m[3] ?? '');
                    $value = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5);
                    if (!array_key_exists($value, $from_to)) {
                        return $m[0];
                    }

                    return 'data-' . $attr . '="' . esc_attr($from_to[$value]) . '"';
                },
                $tag
            );
        }

        return $tag;
    }
}
