<?php
/**
 * Destinations CRUD + per-destination connection test.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\REST;

use WP_REST_Request;
use WP_REST_Response;
use WPEasy\BricksStatic\Deploy\PackageDeployer;
use WPEasy\BricksStatic\Settings\Destinations;
use WPEasy\BricksStatic\Sync\Runner;
use WPEasy\BricksStatic\Sync\Manifest;
use WPEasy\BricksStatic\Transport\TransportFactory;

defined('ABSPATH') || exit;

/**
 * Manages the list of destinations and tests a given destination's connection.
 */
final class DestinationsController {

    /**
     * REST namespace.
     */
    private const NS = 'bs/v1';

    /**
     * Register routes.
     */
    public static function register(): void {
        register_rest_route(self::NS, '/destinations', [
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'index'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'add'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route(self::NS, '/destinations/(?P<id>[A-Za-z0-9]+)', [
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'update'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [self::class, 'remove'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route(self::NS, '/destinations/(?P<id>[A-Za-z0-9]+)/test', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'test'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/destinations/(?P<id>[A-Za-z0-9]+)/package-test', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'package_test'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);
    }

    /**
     * POST /destinations/{id}/package-test — actually run a tiny package deploy to
     * confirm the host can extract it, and re-enable package deploy on success.
     *
     * @param WP_REST_Request $request Request.
     */
    public static function package_test(WP_REST_Request $request): WP_REST_Response {
        $id   = (string) $request['id'];
        $dest = Destinations::get($id);
        if ($dest === null) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Unknown destination.'], 404);
        }

        if (!PackageDeployer::can_build()) {
            return new WP_REST_Response(['ok' => false, 'message' => 'ZipArchive is not available on this server.']);
        }
        $base = PackageDeployer::base_url($dest);
        if ($base === '') {
            return new WP_REST_Response(['ok' => false, 'message' => 'Set a Destination URL first so the helper can be reached.']);
        }

        // get_temp_dir()/tempnam work in every context; wp_tempnam() needs
        // wp-admin/includes/file.php, which isn't loaded on REST/frontend requests.
        $tmp = tempnam(get_temp_dir(), 'bs-pkgtest-');
        if ($tmp === false) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Could not create a temporary file for the test.']);
        }
        file_put_contents($tmp, '<!-- bricks-static package test -->');

        try {
            $transport = TransportFactory::make($dest->connection_config());
            $transport->connect();
            $result = PackageDeployer::deploy(
                $transport,
                $base,
                ['.bs-package-test/probe.html' => $tmp],
                [],
                PackageDeployer::sslverify($base)
            );
            if (!empty($result['ok'])) {
                $transport->delete('.bs-package-test/probe.html');
                $transport->delete('.bs-package-test/probe.html.gz');
            }
            $transport->disconnect();
        } catch (\Throwable $e) {
            @unlink($tmp);
            return new WP_REST_Response(['ok' => false, 'message' => $e->getMessage()]);
        }
        @unlink($tmp);

        if (!empty($result['ok'])) {
            delete_option(PackageDeployer::OFF_PREFIX . $id);
            return new WP_REST_Response(['ok' => true, 'message' => 'Package deploy works — re-enabled.']);
        }

        return new WP_REST_Response(['ok' => false, 'message' => $result['message'] ?? 'Package deploy failed.']);
    }

    /**
     * Capability gate.
     */
    public static function can_manage(): bool {
        return current_user_can('manage_options');
    }

    /**
     * GET /destinations — the list + transport capabilities.
     */
    public static function index(): WP_REST_Response {
        return self::list_response();
    }

    /**
     * POST /destinations — add a destination.
     *
     * @param WP_REST_Request $request Request.
     */
    public static function add(WP_REST_Request $request): WP_REST_Response {
        Destinations::add((array) $request->get_json_params());

        return self::list_response();
    }

    /**
     * POST /destinations/{id} — update a destination.
     *
     * @param WP_REST_Request $request Request.
     */
    public static function update(WP_REST_Request $request): WP_REST_Response {
        $updated = Destinations::update((string) $request['id'], (array) $request->get_json_params());
        if ($updated === null) {
            return new WP_REST_Response(['error' => 'Unknown destination.'], 404);
        }

        return self::list_response();
    }

    /**
     * DELETE /destinations/{id} — remove a destination.
     *
     * @param WP_REST_Request $request Request.
     */
    public static function remove(WP_REST_Request $request): WP_REST_Response {
        if (!Destinations::remove((string) $request['id'])) {
            return new WP_REST_Response(['error' => 'Cannot remove the only destination.'], 400);
        }

        return self::list_response();
    }

    /**
     * POST /destinations/{id}/test — test that destination's connection.
     *
     * @param WP_REST_Request $request Request.
     */
    public static function test(WP_REST_Request $request): WP_REST_Response {
        $dest = Destinations::get((string) $request['id']);
        if ($dest === null) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Unknown destination.'], 200);
        }

        $config = $dest->connection_config();
        $input  = (array) $request->get_json_params();
        foreach (['transport', 'host', 'port', 'username', 'remotePath'] as $key) {
            if (array_key_exists($key, $input) && !$dest->is_from_constant($key)) {
                $config[$key] = $key === 'port' ? (int) $input[$key] : sanitize_text_field((string) $input[$key]);
            }
        }
        if (!empty($input['password']) && !$dest->is_from_constant('password')) {
            $config['password'] = (string) $input['password'];
        }

        if ($config['host'] === '') {
            return new WP_REST_Response(['ok' => false, 'message' => 'Enter a host first.'], 200);
        }

        $transport = TransportFactory::make($config);
        try {
            $transport->connect();
            $ok = true;
            $message = 'Connected successfully.';
        } catch (\Throwable $e) {
            $ok = false;
            $message = $e->getMessage();
        } finally {
            $transport->disconnect();
        }

        update_option(self::conn_state_option((string) $request['id']), ['ok' => $ok, 'time' => time(), 'message' => $message], false);

        return new WP_REST_Response(['ok' => $ok, 'message' => $message], 200);
    }

    /**
     * Option holding a destination's last connection-test result.
     *
     * @param string $id Destination id.
     */
    public static function conn_state_option(string $id): string {
        return 'bs_conn_' . $id;
    }

    /**
     * The destinations list payload, each with its per-destination status.
     */
    private static function list_response(): WP_REST_Response {
        $list = Destinations::for_display();

        foreach ($list as &$dest) {
            $id     = (string) $dest['id'];
            $state  = get_option(self::conn_state_option($id), []);
            $pushed = Manifest::load(Destinations::pushed_option($id));
            $obj    = Destinations::get($id);

            $dest['status'] = [
                'connected' => is_array($state) && !empty($state['ok']),
                'hasPushed' => !empty($pushed),
                // Signature-based: matches the render + this destination's
                // replacements, so a replacement destination isn't false-flagged.
                'inSync'    => !empty($pushed) && Runner::in_sync($id, $obj),
            ];

            // How this destination will be deployed (fast package vs per-file),
            // and whether an explicit Destination URL is set (we guess otherwise).
            $dest['deploy'] = [
                'strategy' => PackageDeployer::available_for($id, $obj) ? 'package' : 'perfile',
                'canBuild' => PackageDeployer::can_build(),
                'hasUrl'   => $obj !== null && trim((string) $obj->get('destinationUrl')) !== '',
                'disabled' => (bool) get_option(PackageDeployer::OFF_PREFIX . $id),
            ];
        }
        unset($dest);

        return new WP_REST_Response([
            'destinations' => $list,
            'capabilities' => TransportFactory::capabilities(),
        ]);
    }
}
