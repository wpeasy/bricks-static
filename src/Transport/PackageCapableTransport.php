<?php
/**
 * Transport that can take a whole deploy as one package.
 *
 * @package WPEasy\ShipStatic
 * @since   2.1.0
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Transport;

defined('ABSPATH') || exit;

/**
 * Implemented by transports whose far end can extract a zip itself (the
 * Connector). `PackageDeployer::deploy()` hands such a transport the built
 * package instead of uploading a one-shot PHP helper over FTP.
 */
interface PackageCapableTransport {

    /**
     * Upload one zip and have the destination extract it + apply deletes.
     *
     * @param string            $zip_path Local zip.
     * @param array<int,string> $deletes  Remote-relative paths to remove.
     * @param callable|null     $progress fn(string $stage) for UI feedback.
     * @return array{ok:bool,retryable:bool,extracted:int,deleted:int,errors:array<int,string>,message:string}
     */
    public function deploy_package(string $zip_path, array $deletes, ?callable $progress = null): array;
}
