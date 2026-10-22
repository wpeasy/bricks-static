<?php
/**
 * Transport that publishes a complete snapshot of the site per deploy.
 *
 * @package WPEasy\BricksStatic
 * @since   2.1.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Transport;

defined('ABSPATH') || exit;

/**
 * Implemented by transports whose far end takes the WHOLE file set on every
 * deploy and swaps it in atomically (Cloudflare). Unlike FTP/SFTP/Connector
 * there is no remote filesystem to diff against and nothing to prune: the far
 * end de-duplicates unchanged files itself, and a file left out of the
 * snapshot simply stops being served.
 *
 * `Runner::start_target()` routes such a destination to its `snapshot` phase,
 * which skips every filesystem-shaped step (.htaccess merge, holding page,
 * home-last swap, `.gz` siblings, package/UnzipScript, prune). See
 * `docs/CLOUDFLARE_PLAN.md` §5.
 */
interface SnapshotCapableTransport {

    /**
     * Publish the complete file set.
     *
     * @param array<string,string> $files    Export-relative path => local file (the FULL deploy set).
     * @param callable|null        $progress fn(string $stage) for UI feedback / heartbeats.
     * @return array{ok:bool,retryable:bool,uploaded:int,skipped:array<int,string>,errors:array<int,string>,message:string}
     *         `uploaded` = files the far end didn't already have; `skipped` =
     *         paths that could not be included (e.g. over the size limit).
     */
    public function deploy_snapshot(array $files, ?callable $progress = null): array;
}
