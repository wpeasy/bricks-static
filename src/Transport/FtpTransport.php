<?php
/**
 * FTP transport (ext-ftp).
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Transport;

defined('ABSPATH') || exit;

/**
 * Plain FTP destination using PHP's ext-ftp.
 *
 * Availability is runtime-detected; the dashboard disables this option when the
 * extension is absent. FTP is unencrypted and slow for many small files — SFTP
 * is preferred where the host supports it.
 */
final class FtpTransport implements TransportInterface {

    /**
     * Default FTP control port.
     */
    private const DEFAULT_PORT = 21;

    /**
     * Connection config: host, port, username, password, remotePath.
     *
     * @var array<string,mixed>
     */
    private array $config;

    /**
     * Active FTP connection resource/object.
     *
     * @var \FTP\Connection|resource|null
     */
    private $conn = null;

    /**
     * @param array<string,mixed> $config Connection config.
     */
    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * Whether ext-ftp is loaded.
     */
    public static function is_available(): bool {
        return function_exists('ftp_connect');
    }

    /**
     * @inheritDoc
     */
    public function connect(): void {
        if (!self::is_available()) {
            throw new \RuntimeException('FTP support (ext-ftp) is not available on this server.');
        }

        $host = (string) ($this->config['host'] ?? '');
        if ($host === '') {
            throw new \RuntimeException('FTP host is required.');
        }

        $port = (int) ($this->config['port'] ?? 0) ?: self::DEFAULT_PORT;
        $conn = @ftp_connect($host, $port, 15);

        if ($conn === false) {
            throw new \RuntimeException(sprintf('Could not connect to FTP host %s:%d.', $host, $port));
        }

        if (!@ftp_login($conn, (string) ($this->config['username'] ?? ''), (string) ($this->config['password'] ?? ''))) {
            ftp_close($conn);
            throw new \RuntimeException('FTP authentication failed. Check username and password.');
        }

        ftp_pasv($conn, true);
        $this->conn = $conn;
    }

    /**
     * @inheritDoc
     */
    public function disconnect(): void {
        if ($this->conn !== null) {
            @ftp_close($this->conn);
            $this->conn = null;
        }
    }

    /**
     * @inheritDoc
     */
    public function put(string $local_path, string $remote_path): void {
        $conn   = $this->require_connection();
        $target = $this->absolute($remote_path);

        $this->ensure_dir(dirname($target));

        if (!@ftp_put($conn, $target, $local_path, FTP_BINARY)) {
            throw new \RuntimeException(sprintf('FTP upload failed for %s.', $remote_path));
        }
    }

    /**
     * @inheritDoc
     */
    public function mkdir(string $remote_path): void {
        $this->require_connection();
        $this->ensure_dir($this->absolute($remote_path));
    }

    /**
     * @inheritDoc
     */
    public function exists(string $remote_path): bool {
        $conn = $this->require_connection();

        return @ftp_size($conn, $this->absolute($remote_path)) !== -1;
    }

    /**
     * Recursively create a remote directory tree.
     *
     * @param string $dir Absolute remote directory.
     */
    private function ensure_dir(string $dir): void {
        $conn = $this->require_connection();
        $dir  = rtrim($dir, '/');
        if ($dir === '' || @ftp_chdir($conn, $dir)) {
            return;
        }

        $parts = explode('/', ltrim($dir, '/'));
        $path  = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $path .= '/' . $part;
            if (!@ftp_chdir($conn, $path)) {
                @ftp_mkdir($conn, $path);
            }
        }
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
     *
     * @return \FTP\Connection|resource
     */
    private function require_connection() {
        if ($this->conn === null) {
            throw new \RuntimeException('FTP is not connected.');
        }

        return $this->conn;
    }
}
