<?php
/**
 * Persisted, resumable Export ZIP job state.
 *
 * @package WPEasy\BricksStatic
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Export;

defined('ABSPATH') || exit;

/**
 * Holds one Export ZIP run's state in a single option so a browser-driven tick
 * loop can resume across requests. Deliberately separate from {@see
 * \WPEasy\BricksStatic\Sync\Job} — export walks a manifest it already has
 * (no page/asset crawl queue), and keeping its own option means it can never
 * collide with a running Sync/Check job's state.
 */
final class ExportJob {

    /**
     * Option storing the active export job.
     */
    public const OPTION = 'bs_export_job';

    /**
     * Phases that mean "not currently running".
     */
    private const TERMINAL = ['done', 'error', 'cancelled'];

    /**
     * Job state.
     *
     * @var array<string,mixed>
     */
    public array $data;

    /**
     * @param array<string,mixed> $data Job state.
     */
    private function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Load the export job, or null if none.
     */
    public static function load(): ?self {
        $data = get_option(self::OPTION);

        return is_array($data) && !empty($data) ? new self($data) : null;
    }

    /**
     * Create and persist a fresh export job for one destination.
     *
     * @param string $dest_id   Destination id.
     * @param string $dest_name Destination display name (for messages/filename).
     */
    public static function create(string $dest_id, string $dest_name): self {
        $job = new self([
            'destId'      => $dest_id,
            'destName'    => $dest_name,
            'phase'       => 'preparing',
            'message'     => __('Preparing export…', 'bricks-static'),
            'files'       => [],
            'manifest'    => [],
            'gzipIndex'   => 0,
            'gzipDone'    => 0,
            'gzipTotal'   => 0,
            'gzipNotice'  => '',
            'packIndex'   => 0,
            'packDone'    => 0,
            'packTotal'   => 0,
            'zipTmpPath'  => '',
            'finalName'   => '',
            'finalPath'   => '',
            'downloadToken' => '',
            'bytes'       => 0,
            'fileCount'   => 0,
            'cancel'      => false,
            'errors'      => [],
            'startedAt'   => time(),
            'updatedAt'   => time(),
        ]);
        $job->save();

        return $job;
    }

    /**
     * Persist the job.
     */
    public function save(): void {
        $this->data['updatedAt'] = time();
        update_option(self::OPTION, $this->data, false);
    }

    /**
     * Delete the job option.
     */
    public static function clear(): void {
        delete_option(self::OPTION);
    }

    /**
     * Whether an export job is currently active (not terminal).
     */
    public static function is_running(): bool {
        $job = self::load();

        return $job !== null && !in_array($job->data['phase'], self::TERMINAL, true);
    }
}
