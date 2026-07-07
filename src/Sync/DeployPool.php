<?php
/**
 * Concurrent multi-destination deploy — the "Concurrent Syncs" worker pool.
 *
 * A full sync renders ONCE (shared, single-driver) and then deploys the render
 * to each enabled destination. By default those deploys run one-after-another.
 * When the site can spawn WP-CLI workers and more than one destination is being
 * pushed, this pool fans the deploy phase out across up to N detached worker
 * processes — each holding its own transport connection to its own destination —
 * so independent remote uploads overlap instead of queueing.
 *
 * This file owns the user-facing "Concurrent syncs" setting; the worker-pool
 * runtime is added alongside it. The sequential path in {@see Runner} stays the
 * untouched fallback whenever the pool is not eligible (concurrency of 1, a
 * single target, or a host that cannot spawn CLI workers).
 *
 * @package WPEasy\BricksStatic
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Sync;

use WPEasy\BricksStatic\CLI\Background;
use WPEasy\BricksStatic\Settings\Destinations;

defined('ABSPATH') || exit;

/**
 * Concurrent-deploy configuration + the worker-pool runtime.
 */
final class DeployPool {

    /**
     * Prefix for a per-destination deploy worker's job state option.
     */
    private const TARGET_PREFIX = 'bs_target_';

    /**
     * Prefix for a per-destination atomic claim row (one worker owns a target).
     */
    private const CLAIM_PREFIX = 'bs_tclaim_';

    /**
     * Seconds a claimed-but-unfinished target may go without its worker updating
     * its state before another worker may STEAL the claim (the original worker is
     * presumed dead). Deliberately generous so a single slow file upload — which
     * only writes state after it completes — is never mistaken for a dead worker.
     */
    private const STEAL_AFTER = 180;

    /**
     * Option storing the user's max-concurrent-deploys preference.
     */
    private const OPTION = 'bs_concurrent_syncs';

    /**
     * Default number of destinations deployed at once.
     */
    public const DEFAULT = 2;

    /**
     * Lowest allowed setting (1 = the classic sequential behaviour).
     */
    public const MIN = 1;

    /**
     * Highest allowed setting — a guard rail against saturating a host's
     * outbound connections / CPU, not a hard protocol limit.
     */
    public const MAX = 10;

    /**
     * The configured maximum number of destinations to deploy concurrently,
     * clamped to [MIN, MAX].
     */
    public static function concurrency(): int {
        $raw = (int) get_option(self::OPTION, self::DEFAULT);

        /**
         * Filters the configured concurrent-deploy count (post-clamp input).
         *
         * @param int $raw Stored value.
         */
        $raw = (int) apply_filters('bs_concurrent_syncs', $raw);

        return self::clamp($raw);
    }

    /**
     * Persist a new concurrency preference (clamped) and return the stored value.
     *
     * @param int $value Requested value.
     */
    public static function set_concurrency(int $value): int {
        $value = self::clamp($value);
        update_option(self::OPTION, $value, false);

        return $value;
    }

    /**
     * Clamp a value into the allowed range.
     *
     * @param int $value Raw value.
     */
    private static function clamp(int $value): int {
        return max(self::MIN, min(self::MAX, $value));
    }

    // ---------------------------------------------------------------------
    // Worker-pool runtime
    // ---------------------------------------------------------------------

    /**
     * The job-state option a given destination's deploy worker persists to.
     *
     * @param string $dest_id Destination id.
     */
    public static function target_option(string $dest_id): string {
        return self::TARGET_PREFIX . $dest_id;
    }

    /**
     * Read a per-target job's state straight from the DB, bypassing the options
     * cache. Essential: the coordinator monitor is a long-lived CLI process, and a
     * per-target job is written by a DIFFERENT (worker) process — get_option()
     * would serve the value cached at first read (typically "missing"), so the
     * monitor would never see a worker's progress or completion and would loop
     * forever. Mirrors is_cancelled()/is_worker_alive()'s cache-bypassing reads.
     *
     * @param string $dest_id Destination id.
     * @return array<string,mixed>|null
     */
    private static function load_target(string $dest_id): ?array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cross-process per-target job read; get_option() serves a stale in-process cache in the long-lived coordinator.
        $val = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            self::target_option($dest_id)
        ));
        if ($val === null) {
            return null;
        }
        $data = maybe_unserialize($val);

        return is_array($data) && !empty($data) ? $data : null;
    }

    /**
     * A unique token identifying this worker process for claim ownership.
     */
    public static function worker_token(): string {
        return uniqid('w', true) . '-' . getmypid();
    }

    /**
     * Clear any per-target job/claim/deploy state left from a previous run for
     * the given destinations, so a fresh parallel deploy starts clean.
     *
     * @param array<int,string> $targets Destination ids.
     */
    public static function reset(array $targets): void {
        foreach ($targets as $dest_id) {
            delete_option(self::target_option($dest_id));
            delete_option(self::CLAIM_PREFIX . $dest_id);
            delete_option(Manifest::DEPLOY_OPTION . '_' . $dest_id);
        }
    }

    /**
     * Spawn up to N deploy-worker processes.
     *
     * @param int $count Worker count.
     * @return int Workers actually launched.
     */
    public static function spawn_workers(int $count): int {
        return Background::spawn_workers($count);
    }

    /**
     * Atomically claim the next unclaimed destination from the main job's target
     * list for this worker. Returns the destination id, or null when every target
     * is already owned by a worker (this worker should then exit).
     *
     * @param string $token This worker's ownership token.
     */
    public static function claim_next_target(string $token): ?string {
        $job = Job::load();
        if ($job === null) {
            return null;
        }

        foreach ((array) ($job->data['targets'] ?? []) as $dest_id) {
            $dest_id = (string) $dest_id;

            // Skip a target that has already been deployed (or errored) — its claim
            // row still belongs to whoever handled it, so without this check the
            // owning worker would re-claim (owner === token) and redeploy it in an
            // infinite loop.
            $tdata = self::load_target($dest_id);
            if ($tdata !== null && in_array((string) ($tdata['phase'] ?? ''), ['done', 'error', 'cancelled'], true)) {
                continue;
            }

            if (self::try_claim($dest_id, $token)) {
                return $dest_id;
            }
        }

        return null;
    }

    /**
     * Try to become the sole owner of a target via an atomic INSERT IGNORE on the
     * claim row (option_name is unique — first writer wins), mirroring
     * Runner::claim_driver.
     *
     * @param string $dest_id Destination id.
     * @param string $token   This worker's token.
     * @return bool Whether this worker won the claim.
     */
    private static function try_claim(string $dest_id, string $token): bool {
        global $wpdb;

        $name = self::CLAIM_PREFIX . $dest_id;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic per-target claim; the options API can't INSERT IGNORE atomically.
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            $name,
            $token
        ));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reads back the claim owner just written; must bypass the options cache.
        $owner = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $name
        ));

        if ($owner === $token) {
            return true; // Won a fresh claim.
        }

        // The target is owned by another worker. Steal it only if that worker has
        // gone silent on an UNFINISHED target (presumed dead) — its per-target job
        // hasn't been touched within STEAL_AFTER. A finished target is never stolen.
        $tdata = self::load_target($dest_id);
        if ($tdata === null) {
            return false; // Just claimed; its job will appear momentarily — leave it.
        }

        $phase = (string) ($tdata['phase'] ?? '');
        if (in_array($phase, ['done', 'error', 'cancelled'], true)) {
            return false; // Already settled — nothing to steal.
        }

        $updated = (int) ($tdata['updatedAt'] ?? 0);
        if ((time() - $updated) <= self::STEAL_AFTER) {
            return false; // Still making progress — hands off.
        }

        // Steal: take ownership and drop the stale per-target job so this worker
        // redeploys the target from scratch (the delta re-pushes only what's missing).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reassign a dead worker's stale claim.
        $wpdb->update($wpdb->options, ['option_value' => $token], ['option_name' => $name]);
        delete_option(self::target_option($dest_id));

        return true;
    }

    /**
     * Aggregate the per-target worker states into an overall progress view for
     * the coordinator's snapshot.
     *
     * @param array<int,string> $targets Destination ids.
     * @return array{
     *     uploaded:int, pruned:int, totalUploads:int, failed:array<int,string>,
     *     done:int, allFinished:bool, anyError:bool,
     *     perTarget:array<int,array<string,mixed>>
     * }
     */
    public static function aggregate(array $targets): array {
        $uploaded = 0;
        $pruned   = 0;
        $total    = 0;
        $failed   = [];
        $done     = 0;
        $finished = 0;
        $anyError = false;
        $noZip    = false;
        $fallback = '';
        $per      = [];

        foreach ($targets as $dest_id) {
            $dest = Destinations::get((string) $dest_id);
            $name = $dest !== null ? (string) $dest->get('name') : (string) $dest_id;

            $tdata = self::load_target((string) $dest_id);
            $phase = $tdata !== null ? (string) ($tdata['phase'] ?? 'pending') : 'pending';

            if ($tdata !== null) {
                $uploaded += (int) ($tdata['counts']['uploaded'] ?? 0);
                $pruned   += (int) ($tdata['counts']['pruned'] ?? 0);
                $total    += (int) ($tdata['totals']['uploads'] ?? 0);
                foreach ((array) ($tdata['failed'] ?? []) as $f) {
                    $failed[] = (string) $f;
                }
                if (!empty($tdata['zipUnavailable'])) {
                    $noZip = true;
                }
                if ($fallback === '' && !empty($tdata['packageFallback'])) {
                    $fallback = (string) $tdata['packageFallback'];
                }
            }

            $status = self::status_of($phase);
            if (in_array($status, ['done', 'error', 'cancelled'], true)) {
                $finished++;
            }
            if ($status === 'done') {
                $done++;
            }
            if ($status === 'error') {
                $anyError = true;
            }

            $per[] = [
                'destId'    => (string) $dest_id,
                'name'      => $name,
                'status'    => $status,
                'uploaded'  => $tdata !== null ? (int) ($tdata['counts']['uploaded'] ?? 0) : 0,
                'total'     => $tdata !== null ? (int) ($tdata['totals']['uploads'] ?? 0) : 0,
                // Package deploy is a single server-side extract — one atomic step,
                // no per-file %; the UI shows an indeterminate bar for it.
                'packaging' => $phase === 'package',
                'message'   => $tdata !== null ? (string) ($tdata['message'] ?? '') : '',
            ];
        }

        return [
            'uploaded'     => $uploaded,
            'pruned'       => $pruned,
            'totalUploads' => $total,
            'failed'       => $failed,
            'done'         => $done,
            'allFinished'  => !empty($targets) && $finished === count($targets),
            'anyError'     => $anyError,
            'zipUnavailable' => $noZip,
            'packageFallback' => $fallback,
            'perTarget'    => $per,
        ];
    }

    /**
     * Map a per-target job phase to a coarse status for the UI.
     *
     * @param string $phase Per-target job phase.
     */
    private static function status_of(string $phase): string {
        switch ($phase) {
            case 'pending':
                return 'pending';
            case 'done':
                return 'done';
            case 'error':
                return 'error';
            case 'cancelled':
                return 'cancelled';
            default:
                return 'active';
        }
    }

    /**
     * Remove the per-target job/claim/deploy state once the run has finished (the
     * pushed manifest + sync signature — the real state — are kept).
     *
     * @param array<int,string> $targets Destination ids.
     */
    public static function cleanup(array $targets): void {
        self::reset($targets);
    }
}
