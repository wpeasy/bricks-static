<?php
/**
 * Per-page internal link graph, recorded during render.
 *
 * Maps each rendered page's WordPress post id to the relative paths of the
 * internal pages it links to (including pages that are currently excluded from
 * the export). Powers the manual-mode "this page links here / is linked from
 * here" integrity checks. Built on every check/sync by {@see Runner}.
 *
 * @package WPEasy\BricksStatic
 * @since   1.0.2
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Sync;

defined('ABSPATH') || exit;

/**
 * Stores `{ sourcePostId => [ linkedRel, … ] }` in a (non-autoloaded) option.
 */
final class LinkGraph {

    /**
     * Option name.
     */
    public const OPTION = 'bs_link_graph';

    /**
     * Load the graph.
     *
     * @return array<int,array<int,string>>
     */
    public static function load(): array {
        $value = get_option(self::OPTION, []);

        return is_array($value) ? $value : [];
    }

    /**
     * Persist the graph.
     *
     * @param array<int,array<int,string>> $graph Source post id => linked rels.
     */
    public static function save(array $graph): void {
        update_option(self::OPTION, $graph, false);
    }

    /**
     * Source post ids whose outbound links include the given relative path —
     * i.e. the pages that link TO the page at `$rel`.
     *
     * @param string $rel Relative path of the linked-to page.
     * @return array<int,int>
     */
    public static function inbound_post_ids(string $rel): array {
        $out = [];
        foreach (self::load() as $post_id => $rels) {
            if (is_array($rels) && in_array($rel, $rels, true)) {
                $out[] = (int) $post_id;
            }
        }

        return $out;
    }
}
