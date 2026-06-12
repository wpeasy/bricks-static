<?php
/**
 * Destination transport contract.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Transport;

defined('ABSPATH') || exit;

/**
 * A pushable destination (FTP or SFTP).
 *
 * All methods throw \RuntimeException on failure so callers can surface a single
 * clean error message.
 */
interface TransportInterface {

    /**
     * Open the connection and authenticate.
     *
     * @throws \RuntimeException On connection or auth failure.
     */
    public function connect(): void;

    /**
     * Close the connection (no-op if not connected).
     */
    public function disconnect(): void;

    /**
     * Upload a local file to a remote path (relative to the remote root),
     * creating parent directories as needed.
     *
     * @param string $local_path  Absolute local file path.
     * @param string $remote_path Remote path relative to the remote root.
     * @throws \RuntimeException On failure.
     */
    public function put(string $local_path, string $remote_path): void;

    /**
     * Recursively create a remote directory (relative to the remote root).
     *
     * @param string $remote_path Remote directory relative to the remote root.
     * @throws \RuntimeException On failure.
     */
    public function mkdir(string $remote_path): void;

    /**
     * Whether a remote path exists (relative to the remote root).
     *
     * @param string $remote_path Remote path relative to the remote root.
     */
    public function exists(string $remote_path): bool;
}
