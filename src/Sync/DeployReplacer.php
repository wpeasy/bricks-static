<?php
/**
 * Contract for a per-destination deploy-time HTML transform.
 *
 * The Runner no longer knows about concrete replacers (media, links, videos,
 * data attributes) — it asks every replacer registered on the {@see Pipeline}
 * to transform each page in turn. Free registers only the Text replacer; the
 * Pro addon registers the rest. This is the seam that lets the Free build ship
 * with zero Pro replacer code.
 *
 * Lifecycle per destination: {@see prepare()} is called once (build the swap
 * tables, collect any extra files to upload), then {@see apply()} runs per HTML
 * file using the prepared context.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.4
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Sync;

use WPEasy\BricksStatic\Settings\Destination;

defined('ABSPATH') || exit;

/**
 * A deploy-time HTML replacer plugged into the {@see Pipeline}.
 */
interface DeployReplacer {

    /**
     * Stable key (e.g. 'text', 'media'). Used to order/identify the replacer in
     * the deploy signature; must be unique and constant across builds.
     */
    public function key(): string;

    /**
     * Prepare this replacer for one destination — called once before the page
     * loop. Returns the context handed back to {@see apply()} plus any extra
     * files (relativePath => absoluteSourcePath) that must be added to the
     * deploy manifest so they upload (e.g. swapped-in media variants).
     *
     * Return a null `ctx` to declare the replacer inactive for this destination
     * (it is then skipped entirely, preserving the "deploy the base verbatim"
     * fast path when nothing needs transforming).
     *
     * @param Destination $dest      Target destination.
     * @param string      $dest_base The destination's public base URL ('' if unknown).
     * @return array{ctx:mixed,files:array<string,string>}
     */
    public function prepare(Destination $dest, string $dest_base): array;

    /**
     * Transform a single HTML document using the prepared context.
     *
     * @param string $html     The (already partially transformed) page HTML.
     * @param mixed  $ctx      The context returned by {@see prepare()}.
     * @param string $relative The page's export-relative path (e.g. 'about/index.html').
     *                         Page-scoped replacers (media, video) use it to apply
     *                         only the swaps saved for this page; global replacers
     *                         (text) ignore it.
     * @return string Transformed HTML (return $html unchanged to no-op).
     */
    public function apply(string $html, $ctx, string $relative): string;

    /**
     * A JSON-serialisable value folded into a destination's deploy signature, so
     * a change to this replacer's settings marks the destination out of sync.
     *
     * @param Destination $dest Target destination.
     * @return mixed
     */
    public function signature(Destination $dest);
}
