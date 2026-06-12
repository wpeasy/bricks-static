<?php
/**
 * SFTP transport (phpseclib).
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Transport;

use phpseclib3\Net\SFTP;

defined('ABSPATH') || exit;

/**
 * SFTP destination using the bundled phpseclib library (no ext-ssh2 required).
 */
final class SftpTransport implements TransportInterface {

    /**
     * Default SFTP port.
     */
    private const DEFAULT_PORT = 22;

    /**
     * Connection config: host, port, username, password, remotePath.
     *
     * @var array<string,mixed>
     */
    private array $config;

    /**
     * Active connection.
     *
     * @var SFTP|null
     */
    private ?SFTP $sftp = null;

    /**
     * @param array<string,mixed> $config Connection config.
     */
    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * Whether phpseclib is available.
     */
    public static function is_available(): bool {
        return class_exists(SFTP::class);
    }

    /**
     * @inheritDoc
     */
    public function connect(): void {
        if (!self::is_available()) {
            throw new \RuntimeException('SFTP support (phpseclib) is not available.');
        }

        $host = (string) ($this->config['host'] ?? '');
        if ($host === '') {
            throw new \RuntimeException('SFTP host is required.');
        }

        $port = (int) ($this->config['port'] ?? 0) ?: self::DEFAULT_PORT;
        $sftp = new SFTP($host, $port, 15);

        if (!$sftp->login((string) ($this->config['username'] ?? ''), (string) ($this->config['password'] ?? ''))) {
            throw new \RuntimeException('SFTP authentication failed. Check host, port, username and password.');
        }

        $this->sftp = $sftp;
    }

    /**
     * @inheritDoc
     */
    public function disconnect(): void {
        if ($this->sftp !== null) {
            $this->sftp->disconnect();
            $this->sftp = null;
        }
    }

    /**
     * @inheritDoc
     */
    public function put(string $local_path, string $remote_path): void {
        $sftp   = $this->require_connection();
        $target = $this->absolute($remote_path);

        $dir = dirname($target);
        if ($dir !== '' && $dir !== '.' && !$sftp->is_dir($dir)) {
            $sftp->mkdir($dir, -1, true);
        }

        if (!$sftp->put($target, $local_path, SFTP::SOURCE_LOCAL_FILE)) {
            throw new \RuntimeException(sprintf('SFTP upload failed for %s.', $remote_path));
        }
    }

    /**
     * @inheritDoc
     */
    public function mkdir(string $remote_path): void {
        $sftp = $this->require_connection();
        $dir  = $this->absolute($remote_path);

        if (!$sftp->is_dir($dir) && !$sftp->mkdir($dir, -1, true)) {
            throw new \RuntimeException(sprintf('SFTP mkdir failed for %s.', $remote_path));
        }
    }

    /**
     * @inheritDoc
     */
    public function exists(string $remote_path): bool {
        return $this->require_connection()->file_exists($this->absolute($remote_path));
    }

    /**
     * @inheritDoc
     */
    public function rename(string $from, string $to): bool {
        return $this->require_connection()->rename($this->absolute($from), $this->absolute($to));
    }

    /**
     * @inheritDoc
     */
    public function get(string $remote_path): string {
        $data = $this->require_connection()->get($this->absolute($remote_path));

        return is_string($data) ? $data : '';
    }

    /**
     * Resolve a path relative to the configured remote root.
     *
     * @param string $remote_path Relative remote path.
     */
    private function absolute(string $remote_path): string {
        $root = trim((string) ($this->config['remotePath'] ?? ''), '/');
        $rel  = ltrim($remote_path, '/');

        return ($root === '' ? '' : '/' . $root) . '/' . $rel;
    }

    /**
     * Get the active connection or fail.
     */
    private function require_connection(): SFTP {
        if ($this->sftp === null) {
            throw new \RuntimeException('SFTP is not connected.');
        }

        return $this->sftp;
    }
}
