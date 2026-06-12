<?php
/**
 * Persisted, resumable sync job state.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Sync;

use WPEasy\BricksStatic\Support\Url;

defined('ABSPATH') || exit;

/**
 * Holds a run's queue, seen-set, counts and errors in a single option so a
 * browser-driven tick loop can resume across requests. Queue items are URLs;
 * dedupe is by page-URL (pages) and by target file path (assets).
 */
final class Job {

    /**
     * Option storing the active job.
     */
    private const OPTION = 'bs_job';

    /**
     * Path segments that are never rendered (matched at segment boundaries).
     */
    private const EXCLUDED_PATTERN = '#(^|/)(wp-admin|wp-login\.php|wp-json|xmlrpc\.php|wp-cron\.php|feed|trackback)(/|$)#i';

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
     * Load the active job, or null if none.
     */
    public static function load(): ?self {
        $data = get_option(self::OPTION);

        return is_array($data) && !empty($data) ? new self($data) : null;
    }

    /**
     * Create and persist a fresh job.
     *
     * @param string $type Run type ('check' for a dry run, 'sync' for a push).
     */
    public static function create(string $type): self {
        $job = new self([
            'type'      => $type,
            'phase'     => 'collect',
            'queue'        => ['pages' => [], 'assets' => [], 'uploads' => []],
            'seen'         => ['pages' => [], 'assets' => []],
            // Upload plan: output relative path => absolute local source file.
            // Cached files (pages, rewritten CSS) point into the cache; binary
            // assets point straight at the source filesystem (never copied).
            'plan'         => [],
            'counts'       => ['pagesDone' => 0, 'assetsDone' => 0, 'uploaded' => 0, 'bytes' => 0, 'files' => 0],
            'totals'       => ['pages' => 0, 'assets' => 0, 'uploads' => 0],
            'htaccessDone' => false,
            'holdingShown' => false,
            'failed'       => [],
            'removed'      => 0,
            'errors'    => [],
            'skipped'   => [],
            'cancel'    => false,
            'message'   => 'Collecting URLs…',
            'startedAt' => time(),
            'updatedAt' => time(),
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
     * Delete the active job.
     */
    public static function clear(): void {
        delete_option(self::OPTION);
    }

    /**
     * Queue a page URL for rendering (deduped, filtered, query-less only).
     *
     * @param string $url Absolute URL.
     */
    public function enqueue_page(string $url): void {
        if (!Url::is_internal($url)) {
            return;
        }

        if (wp_parse_url($url, PHP_URL_QUERY)) {
            return; // A page with a query string can't be a static file.
        }

        $path = (string) (wp_parse_url($url, PHP_URL_PATH) ?? '/');
        if (preg_match(self::EXCLUDED_PATTERN, $path) || strpos($path, '@') !== false) {
            return; // Excluded path, or a mis-parsed email/text link.
        }

        if (Url::to_relative_path($url) === null || isset($this->data['seen']['pages'][$url])) {
            return;
        }

        $this->data['seen']['pages'][$url] = true;
        $this->data['queue']['pages'][]    = $url;
    }

    /**
     * Queue an asset URL for copying (deduped by target file path).
     *
     * @param string $url Absolute URL.
     */
    public function enqueue_asset(string $url): void {
        if (!Url::is_internal($url)) {
            return;
        }

        $path = (string) (wp_parse_url($url, PHP_URL_PATH) ?? '');
        if (preg_match(self::EXCLUDED_PATTERN, $path)) {
            return; // Don't mirror feeds, wp-json, etc. linked as <link href>.
        }

        $rel = Url::to_relative_path($url);
        if ($rel === null || isset($this->data['seen']['assets'][$rel])) {
            return;
        }

        $this->data['seen']['assets'][$rel] = true;
        $this->data['queue']['assets'][]    = $url;
    }

    /**
     * Record a skipped URL.
     *
     * @param string $url    URL.
     * @param string $reason Reason.
     */
    public function skip(string $url, string $reason): void {
        $this->data['skipped'][] = ['url' => $url, 'reason' => $reason];
    }

    /**
     * Record an error.
     *
     * @param string $url   URL.
     * @param string $error Message.
     */
    public function error(string $url, string $error): void {
        $this->data['errors'][] = ['url' => $url, 'error' => $error];
    }
}
