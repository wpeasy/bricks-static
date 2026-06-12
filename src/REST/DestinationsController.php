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
use WPEasy\BricksStatic\Settings\Destinations;
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

        return new WP_REST_Response(['ok' => $ok, 'message' => $message], 200);
    }

    /**
     * The destinations list payload.
     */
    private static function list_response(): WP_REST_Response {
        return new WP_REST_Response([
            'destinations' => Destinations::for_display(),
            'capabilities' => TransportFactory::capabilities(),
        ]);
    }
}
