<?php
/**
 * Flags page content that won't work on a static copy.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Render;

defined('ABSPATH') || exit;

/**
 * Scans rendered HTML for things that depend on PHP/WordPress at request time —
 * forms and dynamic links — which silently break once served as flat files.
 * Each finding carries the actual link so the user can see what was flagged.
 * WordPress-core boilerplate (login, xmlrpc, RSD, feeds in <head>) is ignored.
 */
final class CompatibilityScanner {

    /**
     * WP-core scripts that aren't user-facing functionality (ignored).
     */
    private const BOILERPLATE = '#/(wp-login|xmlrpc|wp-signup|wp-register|wp-comments-post|wp-cron|wp-trackback)\.php#i';

    /**
     * Return the distinct compatibility findings in a page's HTML.
     *
     * @param string $html Rendered HTML.
     * @return array<int,array{type:string,link:string}>
     */
    public static function scan(string $html): array {
        $found = [];
        $seen  = [];

        // Forms (real <form> elements) — submissions won't work statically.
        if (preg_match_all('#<form\b[^>]*>#i', $html, $forms)) {
            foreach ($forms[0] as $tag) {
                $type   = preg_match('#\b(role="search"|class="[^"]*\bsearch)#i', $tag) ? 'search-form' : 'form';
                $action = preg_match('#\saction\s*=\s*("([^"]*)"|\'([^\']*)\')#i', $tag, $m)
                    ? ($m[2] !== '' ? $m[2] : ($m[3] ?? ''))
                    : '';
                self::add($found, $seen, $type, $action);
            }
        }

        // Anchor links to a .php script (excluding WP-core boilerplate).
        if (preg_match_all('#<a\b[^>]*\shref\s*=\s*("([^"]*\.php\b[^"]*)"|\'([^\']*\.php\b[^\']*)\')#i', $html, $links, PREG_SET_ORDER)) {
            foreach ($links as $m) {
                $href = $m[2] !== '' ? $m[2] : ($m[3] ?? '');
                if ($href !== '' && !preg_match(self::BOILERPLATE, $href)) {
                    self::add($found, $seen, 'php-link', $href);
                }
            }
        }

        return $found;
    }

    /**
     * Append a deduped finding.
     *
     * @param array<int,array{type:string,link:string}> $found Findings (by ref).
     * @param array<string,bool>                         $seen  Dedupe set (by ref).
     * @param string                                     $type  Finding type.
     * @param string                                     $link  Associated link.
     */
    private static function add(array &$found, array &$seen, string $type, string $link): void {
        $key = $type . '|' . $link;
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key]    = true;
        $found[]       = ['type' => $type, 'link' => $link];
    }
}
