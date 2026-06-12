<?php
/**
 * WP-CLI command for rendering and syncing the static site.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\CLI;

use WPEasy\BricksStatic\Sync\Runner;

defined('ABSPATH') || exit;

/**
 * Runs the sync from the command line.
 *
 * This is the reliable way to render on hosts that serialize PHP requests
 * (e.g. Local on Windows): a CLI process isn't a web request, so its loopback
 * page renders aren't competing with it for a web worker.
 */
final class SyncCommand {

    /**
     * Render the site and push it to the destination.
     *
     * ## OPTIONS
     *
     * [--check]
     * : Dry run — render locally and report the delta, without uploading.
     *
     * [--prune]
     * : Also remove destination files that no longer exist locally (sync only).
     *
     * ## EXAMPLES
     *
     *     wp bricks-static sync
     *     wp bricks-static sync --check
     *     wp bricks-static sync --prune
     *
     * @param array<int,string>    $args       Positional args (unused).
     * @param array<string,string> $assoc_args Flags.
     * @when after_wp_load
     */
    public function sync(array $args, array $assoc_args): void {
        $type  = isset($assoc_args['check']) ? 'check' : 'sync';
        $prune = isset($assoc_args['prune']);

        \WP_CLI::log(sprintf('Starting %s%s…', $type, $prune ? ' (prune)' : ''));

        try {
            $snapshot = Runner::start($type, ['prune' => $prune]);
        } catch (\Throwable $e) {
            \WP_CLI::error($e->getMessage());
            return;
        }

        $last_phase = '';
        while (!empty($snapshot['running'])) {
            $snapshot = Runner::tick();

            if (($snapshot['phase'] ?? '') !== $last_phase) {
                \WP_CLI::log('  ' . ($snapshot['message'] ?? $snapshot['phase']));
                $last_phase = $snapshot['phase'];
            }
        }

        $counts = $snapshot['counts'] ?? [];
        \WP_CLI::log(sprintf(
            'Pages: %d, assets: %d, files: %d, uploaded: %d, removed: %d, errors: %d, skipped: %d',
            $counts['pagesDone'] ?? 0,
            $counts['assetsDone'] ?? 0,
            $counts['files'] ?? 0,
            $counts['uploaded'] ?? 0,
            $counts['pruned'] ?? 0,
            $snapshot['errorCount'] ?? 0,
            $snapshot['skippedCount'] ?? 0
        ));

        foreach (array_slice($snapshot['errors'] ?? [], 0, 25) as $error) {
            \WP_CLI::warning(sprintf('%s — %s', $error['url'], $error['error']));
        }

        if (($snapshot['compatCount'] ?? 0) > 0) {
            \WP_CLI::warning(sprintf('%d page(s) contain forms or dynamic endpoints that will not work on the static copy:', $snapshot['compatCount']));
            foreach (array_slice($snapshot['compat'] ?? [], 0, 10) as $c) {
                \WP_CLI::log('  - ' . $c['url'] . ' (' . implode(', ', $c['issues']) . ')');
            }
        }

        if (($snapshot['phase'] ?? '') === 'done') {
            \WP_CLI::success((string) ($snapshot['message'] ?? 'Done.'));
        } else {
            \WP_CLI::error((string) ($snapshot['message'] ?? 'Finished with errors.'));
        }
    }

    /**
     * Drive an already-started job to completion (used by the dashboard's
     * auto-run; not normally invoked by hand).
     *
     * @param array<int,string>    $args       Positional args (unused).
     * @param array<string,string> $assoc_args Flags (unused).
     * @when after_wp_load
     */
    public function run(array $args, array $assoc_args): void {
        $snapshot = Runner::status();

        if (($snapshot['phase'] ?? 'idle') === 'idle') {
            \WP_CLI::warning('No active job to run.');
            return;
        }

        while (!empty($snapshot['running'])) {
            $snapshot = Runner::tick();
        }

        \WP_CLI::success((string) ($snapshot['message'] ?? 'Done.'));
    }
}
