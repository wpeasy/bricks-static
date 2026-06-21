<?php
/**
 * The Text deploy replacer — the one replacer shipped in Free.
 *
 * Wraps {@see TextReplacer} in the {@see DeployReplacer} contract so it plugs
 * into the {@see \WPEasy\BricksStatic\Sync\Pipeline} the same way the Pro
 * replacers do. Keeping Free on the same seam means the pipeline path is always
 * exercised, not just when Pro is present.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.4
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Render;

use WPEasy\BricksStatic\Settings\Destination;
use WPEasy\BricksStatic\Sync\DeployReplacer;

defined('ABSPATH') || exit;

/**
 * Applies a destination's literal text search/replace pairs at deploy time.
 */
final class TextDeployReplacer implements DeployReplacer {

    /**
     * {@inheritDoc}
     */
    public function key(): string {
        return 'text';
    }

    /**
     * {@inheritDoc}
     */
    public function prepare(Destination $dest, string $dest_base): array {
        $text = $dest->replacements();
        if (empty($text)) {
            return ['ctx' => null, 'files' => []];
        }

        return [
            'ctx'   => [
                'searches' => array_column($text, 'search'),
                'replaces' => array_column($text, 'replace'),
            ],
            'files' => [],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function apply(string $html, $ctx): string {
        return TextReplacer::apply($html, $ctx['searches'], $ctx['replaces']);
    }

    /**
     * {@inheritDoc}
     */
    public function signature(Destination $dest) {
        return $dest->replacements();
    }
}
