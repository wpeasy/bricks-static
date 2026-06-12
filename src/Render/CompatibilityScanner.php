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
 * forms and dynamic endpoints — which silently break once served as flat files.
 * Findings are reported (not blocked) so the user knows what to expect.
 */
final class CompatibilityScanner {

    /**
     * Issue label => detection regex. Deliberately narrow: we flag user-facing
     * dynamic bits (real forms, anchors to PHP), NOT the wp-json / xmlrpc / RSD
     * boilerplate every WordPress <head> carries (harmless on a static copy).
     */
    private const CHECKS = [
        // A real <form> (contact/search/comment/login) — submissions won't work.
        'form'        => '#<form\b(?![^>]*\bclass="[^"]*\bsearch)#i',
        // A header/footer-style search form.
        'search-form' => '#<form\b[^>]*\b(?:role="search"|class="[^"]*\bsearch)#i',
        // A user-facing link that targets a PHP script.
        'php-link'    => '#<a\b[^>]*\shref\s*=\s*["\'][^"\']*\.php\b#i',
    ];

    /**
     * Return the distinct compatibility issues found in a page's HTML.
     *
     * @param string $html Rendered HTML.
     * @return array<int,string> Issue labels (e.g. ['form','admin-ajax']).
     */
    public static function scan(string $html): array {
        $found = [];

        foreach (self::CHECKS as $label => $pattern) {
            if (preg_match($pattern, $html)) {
                $found[] = $label;
            }
        }

        return $found;
    }
}
