<?php
/**
 * Snapshot transport whose publish can span many ticks.
 *
 * @package WPEasy\ShipStatic
 * @since   2.1.3
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Transport;

defined('ABSPATH') || exit;

/**
 * A snapshot transport (GitHub Pages) whose far end rate-limits writes
 * tightly enough that a large first publish can't finish in one tick — GitHub
 * allows ~500 content-creating requests an hour. `Runner::tick_snapshot()`
 * calls {@see deploy_snapshot_step()} each tick, persisting `$state` in the
 * job between calls, until the result is no longer `pending`.
 *
 * Nothing is visible on the far end until the final step (a GitHub commit +
 * branch move), so cancelling between steps leaves the published site as it
 * was. See `docs/GITHUB_PAGES_PLAN.md` §7.
 */
interface ResumableSnapshotTransport extends SnapshotCapableTransport {

    /**
     * Do as much of the publish as fits in one tick.
     *
     * @param array<string,string> $files    Export-relative path => local file (the FULL deploy set).
     * @param array<string,mixed>  $state    In/out: the transport's progress; start with [].
     * @param callable|null        $progress fn(string $stage) for UI feedback / heartbeats.
     * @return array{ok:bool,pending:bool,waitUntil:int,retryable:bool,uploaded:int,skipped:array<int,string>,errors:array<int,string>,message:string}
     *         `pending` = call again with the same $state; `waitUntil` = Unix
     *         time not to call again before (0 = straight away).
     */
    public function deploy_snapshot_step(array $files, array &$state, ?callable $progress = null): array;
}
