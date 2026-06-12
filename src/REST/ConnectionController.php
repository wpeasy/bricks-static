<?php
/**
 * Connection + settings REST controller.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\REST;

use WP_REST_Request;
use WP_REST_Response;
use WPEasy\BricksStatic\Settings\Settings;
use WPEasy\BricksStatic\Transport\TransportFactory;

defined('ABSPATH') || exit;

/**
 * Routes for reading/saving destination settings and testing the connection.
 */
final class ConnectionController {

    /**
     * REST namespace.
     */
    private const NS = 'bs/v1';

    /**
     * Register routes.
     */
    public static function register(): void {
        register_rest_route(self::NS, '/connection', [
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'get'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'save'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route(self::NS, '/connection/test', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'test'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);
    }

    /**
     * Capability gate for all routes.
     */
    public static function can_manage(): bool {
        return current_user_can('manage_options');
    }

    /**
     * GET /connection — current settings (no secrets) + transport capabilities.
     */
    public static function get(): WP_REST_Response {
        return new WP_REST_Response([
            'settings'     => Settings::for_display(),
            'capabilities' => TransportFactory::capabilities(),
        ]);
    }

    /**
     * POST /connection — persist settings.
     *
     * @param WP_REST_Request $request Request.
     */
    public static function save(WP_REST_Request $request): WP_REST_Response {
        $input = (array) $request->get_json_params();
        $saved = Settings::save($input);

        // Credentials/host may have changed — invalidate the cached test result.
        delete_option('bs_connection_state');

        return new WP_REST_Response([
            'settings'     => $saved,
            'capabilities' => TransportFactory::capabilities(),
        ]);
    }

    /**
     * POST /connection/test — attempt to connect with the saved settings,
     * overlaid with any values supplied in the request (e.g. an unsaved form).
     *
     * @param WP_REST_Request $request Request.
     */
    public static function test(WP_REST_Request $request): WP_REST_Response {
        $config = self::config_from_request($request);

        if ($config['host'] === '') {
            return new WP_REST_Response(['ok' => false, 'message' => 'Enter a host first.'], 200);
        }

        $transport = TransportFactory::make($config);

        try {
            $transport->connect();
            $ok      = true;
            $message = 'Connected successfully.';
        } catch (\Throwable $e) {
            $ok      = false;
            $message = $e->getMessage();
        } finally {
            $transport->disconnect();
        }

        update_option('bs_connection_state', [
            'ok'      => $ok,
            'time'    => time(),
            'message' => $message,
        ], false);

        return new WP_REST_Response(['ok' => $ok, 'message' => $message], 200);
    }

    /**
     * Build a connection config from saved settings, overlaid with request values.
     *
     * The password is only overridden when the request supplies a non-empty one,
     * so "Test" works against the saved password without the UI resending it.
     *
     * @param WP_REST_Request $request Request.
     * @return array<string,mixed>
     */
    private static function config_from_request(WP_REST_Request $request): array {
        $config = Settings::connection_config();
        $input  = (array) $request->get_json_params();

        foreach (['transport', 'host', 'port', 'username', 'remotePath'] as $key) {
            if (array_key_exists($key, $input) && !Settings::is_from_constant($key)) {
                $config[$key] = $key === 'port' ? (int) $input[$key] : sanitize_text_field((string) $input[$key]);
            }
        }

        if (!empty($input['password']) && !Settings::is_from_constant('password')) {
            $config['password'] = (string) $input['password'];
        }

        return $config;
    }
}
