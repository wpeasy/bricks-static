<?php
/**
 * Credential encryption.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Support;

defined('ABSPATH') || exit;

/**
 * AES-256-GCM encryption for stored secrets (FTP/SFTP passwords).
 *
 * The key is derived from the site's WordPress salts, so an encrypted value is
 * only decryptable on the same install. This protects credentials at rest in
 * the options table; it is not a defence against a fully-compromised server,
 * where the key material is also readable (use wp-config constants for that).
 */
final class Crypto {

    /**
     * Cipher used for credential storage.
     */
    private const CIPHER = 'aes-256-gcm';

    /**
     * Prefix marking a value as produced by this class.
     */
    private const PREFIX = 'bsenc:';

    /**
     * Whether the runtime can encrypt/decrypt.
     */
    public static function available(): bool {
        return function_exists('openssl_encrypt')
            && in_array(self::CIPHER, openssl_get_cipher_methods(), true);
    }

    /**
     * Whether a stored value is one of ours.
     *
     * @param string $value Stored value.
     */
    public static function is_encrypted(string $value): bool {
        return strncmp($value, self::PREFIX, strlen(self::PREFIX)) === 0;
    }

    /**
     * Encrypt a plaintext secret.
     *
     * @param string $plaintext Secret to encrypt.
     * @return string Encoded ciphertext, or '' if input is empty / unavailable.
     */
    public static function encrypt(string $plaintext): string {
        if ($plaintext === '' || !self::available()) {
            return '';
        }

        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt($plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($ct === false) {
            return '';
        }

        return self::PREFIX . base64_encode($iv . $tag . $ct);
    }

    /**
     * Decrypt a value produced by encrypt().
     *
     * @param string $payload Encoded ciphertext.
     * @return string Plaintext, or '' on failure.
     */
    public static function decrypt(string $payload): string {
        if (!self::is_encrypted($payload) || !self::available()) {
            return '';
        }

        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);

        if ($raw === false || strlen($raw) < 28) {
            return '';
        }

        $iv  = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct  = substr($raw, 28);
        $pt  = openssl_decrypt($ct, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        return $pt === false ? '' : $pt;
    }

    /**
     * Derive a 32-byte key from the site's WordPress salts.
     */
    private static function key(): string {
        $material = '';

        foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT'] as $const) {
            if (defined($const)) {
                $material .= (string) constant($const);
            }
        }

        if ($material === '') {
            $material = wp_salt('auth');
        }

        return hash('sha256', 'bricks-static|' . $material, true);
    }
}
