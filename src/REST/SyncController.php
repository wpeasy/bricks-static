<?php
/**
 * Sync REST controller (start / tick / status / cancel).
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\REST;

use WP_REST_Request;
use WP_REST_Response;
use WPEasy\BricksStatic\CLI\Background;
use WPEasy\BricksStatic\Render\PageRenderer;
use WPEasy\BricksStatic\Settings\Destinations;
use WPEasy\BricksStatic\Sync\HtaccessBuilder;
use WPEasy\BricksStatic\Sync\Job;
use WPEasy\BricksStatic\Sync\Manifest;
use WPEasy\BricksStatic\Sync\Runner;

defined('ABSPATH') || exit;

/**
 * Drives the browser-driven batched run: start a job, then poll tick() until it
 * is no longer running. M2 supports the "check" (dry-run) type; "sync" (push)
 * lands in M3.
 */
final class SyncController {

    /**
     * REST namespace.
     */
    private const NS = 'bs/v1';

    /**
     * Register routes.
     */
    public static function register(): void {
        register_rest_route(self::NS, '/sync/start', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'start'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/sync/tick', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'tick'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/sync', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'status'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/sync/cancel', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'cancel'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/sync/retry', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'retry'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/sync/claim', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'claim'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/sync/server-config', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'server_config'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/sync/reset', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'reset'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/sync/preflight', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'preflight'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);
    }

    /**
     * Capability gate.
     */
    public static function can_manage(): bool {
        return current_user_can('manage_options');
    }

    /**
     * POST /sync/start — begin a run.
     *
     * @param WP_REST_Request $request Request.
     */
    public static function start(WP_REST_Request $request): WP_REST_Response {
        $params = (array) $request->get_json_params();
        $type   = (string) ($params['type'] ?? 'check');

        if (!in_array($type, ['check', 'sync'], true)) {
            return new WP_REST_Response(['error' => 'Unknown run type.'], 400);
        }

        $options = ['prune' => !empty($params['prune'])];
        $dest    = (string) ($params['dest'] ?? '');
        if ($type === 'sync') {
            if ($dest === 'all') {
                $options['targets'] = Destinations::enabled_ids();
            } elseif ($dest !== '') {
                $options['targets'] = [$dest];
            }
        }

        try {
            $snapshot = Runner::start($type, $options);

            // Prefer WP-CLI where the host can spawn it (no web-worker contention);
            // otherwise the browser drives the run via curl/loopback ticks.
            $snapshot['driver'] = Background::spawn_run() ? 'cli' : 'browser';

            return new WP_REST_Response($snapshot);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['phase' => 'error', 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * POST /sync/retry — re-upload the files that failed to push, without
     * re-rendering. Driven the same way as a fresh run.
     */
    public static function retry(): WP_REST_Response {
        try {
            $snapshot           = Runner::start_retry();
            $snapshot['driver'] = Background::spawn_run() ? 'cli' : 'browser';

            return new WP_REST_Response($snapshot);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['phase' => 'error', 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * POST /sync/claim — attempt to claim the browser as the job's driver.
     * Returns the winning owner ('cli' | 'browser'); the browser only ticks if it
     * gets 'browser', so it never double-drives a live CLI process.
     */
    public static function claim(): WP_REST_Response {
        return new WP_REST_Response(['owner' => Runner::claim_driver('browser')]);
    }

    /**
     * POST /sync/tick — process one batch.
     */
    public static function tick(): WP_REST_Response {
        try {
            return new WP_REST_Response(Runner::tick());
        } catch (\Throwable $e) {
            return new WP_REST_Response(['phase' => 'error', 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * GET /sync — current job snapshot.
     */
    public static function status(): WP_REST_Response {
        return new WP_REST_Response(Runner::status());
    }

    /**
     * POST /sync/cancel — request cancellation.
     */
    public static function cancel(): WP_REST_Response {
        Runner::cancel();

        return new WP_REST_Response(Runner::status());
    }

    /**
     * POST /sync/reset — clear local sync state so the next sync re-uploads
     * everything (e.g. after switching destinations or a remote wipe).
     */
    public static function reset(): WP_REST_Response {
        Job::clear();

        // Clear EVERY destination's push record, not just the primary's — a reset
        // means "forget what's on the remotes" so the next sync uploads in full.
        foreach (Destinations::all() as $dest) {
            delete_option(Destinations::pushed_option((string) ($dest['id'] ?? '')));
        }

        delete_option(Manifest::RENDER_OPTION);

        return new WP_REST_Response(['ok' => true]);
    }

    /**
     * POST /sync/preflight — a one-shot loopback render of the homepage from
     * within this request. If it can't get a worker (serialized hosts like
     * Local) it times out, telling us browser-driven sync won't work here.
     */
    public static function preflight(): WP_REST_Response {
        $url            = home_url('/');
        $args           = PageRenderer::request_args($url);
        $args['timeout'] = 8;

        $start    = microtime(true);
        $response = wp_remote_get($url, $args);
        $ms       = (int) round((microtime(true) - $start) * 1000);

        if (is_wp_error($response)) {
            return new WP_REST_Response(['ok' => false, 'ms' => $ms, 'message' => $response->get_error_message()]);
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        return new WP_REST_Response([
            'ok'      => $code === 200,
            'ms'      => $ms,
            'message' => $code === 200 ? "Rendered the homepage in {$ms}ms." : "Unexpected response (HTTP {$code}).",
        ]);
    }

    /**
     * GET /sync/server-config — the .htaccess (uploaded automatically) and the
     * nginx snippet (for manual paste).
     */
    public static function server_config(): WP_REST_Response {
        return new WP_REST_Response([
            'htaccess' => HtaccessBuilder::htaccess(),
            'nginx'    => HtaccessBuilder::nginx(),
        ]);
    }
}
