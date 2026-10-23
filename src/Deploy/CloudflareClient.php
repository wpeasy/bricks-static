<?php
/**
 * Cloudflare API client (Workers static assets).
 *
 * @package WPEasy\ShipStatic
 * @since   2.1.1
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Deploy;

defined('ABSPATH') || exit;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception messages are plain text for JSON responses (rendered as text by the dashboard) and WP-CLI, never echoed into HTML; esc_html() here would show entities to the user.

/**
 * Speaks the Cloudflare v4 API for a Workers static-assets deploy — see
 * `docs/CLOUDFLARE_PLAN.md` §4 for the flow:
 *
 *   1. `start_session()` — send the FULL manifest; Cloudflare answers with the
 *      hashes it doesn't already have, grouped into buckets, plus an upload JWT.
 *   2. `upload_bucket()` — one multipart request per bucket (base64 bodies),
 *      authenticated with that JWT; the last one returns a completion JWT.
 *   3. `publish()` — PUT the Worker with only a `metadata` part (an assets-only
 *      Worker: no script), which makes the new asset set live atomically.
 *
 * Every request goes to the fixed host `api.cloudflare.com` — never a
 * user-supplied URL — so the `UrlSafety` SSRF guard doesn't apply; the
 * user-supplied pieces (account id, Worker name) are validated to strict
 * character sets by `Settings\Schema` and again here before being put in a path.
 * TLS is always verified (SECURITY_PATTERNS.md §2, §3c). The API token and the
 * JWTs are never logged or included in an exception message.
 */
final class CloudflareClient {

    /**
     * API base. Fixed — never derived from settings.
     */
    private const API = 'https://api.cloudflare.com/client/v4';

    /**
     * Attempts per request on a transport error, 429 or 5xx.
     */
    private const TRIES = 3;

    /**
     * Compatibility date sent with each publish. Only affects Worker runtime
     * behaviour, and an assets-only Worker runs no code of ours.
     */
    private const COMPATIBILITY_DATE = '2025-01-01';

    private string $account;

    private string $token;

    /**
     * @param string $account Cloudflare account id (32 hex).
     * @param string $token   API token (Account → Workers Scripts → Edit).
     */
    public function __construct(string $account, string $token) {
        $this->account = strtolower(trim($account));
        $this->token   = trim($token);
    }

    /**
     * Whether an account id + token are present and well-formed.
     */
    public function configured(): bool {
        return self::valid_account($this->account) && $this->token !== '';
    }

    /**
     * Whether a string is a Cloudflare account id (32 lowercase hex).
     *
     * @param string $account Candidate.
     */
    public static function valid_account(string $account): bool {
        return (bool) preg_match('/^[a-f0-9]{32}$/', $account);
    }

    /**
     * Whether a string is a valid Worker name (lowercase letters, digits and
     * dashes, 1–63 chars, no leading/trailing dash).
     *
     * @param string $name Candidate.
     */
    public static function valid_worker(string $name): bool {
        return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $name);
    }

    /**
     * The account's workers.dev subdomain (the `<sub>` in
     * `<worker>.<sub>.workers.dev`), or '' when the account hasn't registered
     * one yet. Also serves as the connection test: it fails on a bad token, a
     * wrong account id or a token without Workers access.
     *
     * @throws \RuntimeException On auth/API failure.
     */
    public function account_subdomain(): string {
        try {
            $result = $this->request('GET', '/accounts/' . $this->account . '/workers/subdomain', [], $this->token);
        } catch (CloudflareNotFoundException $e) {
            return ''; // No workers.dev subdomain registered on this account.
        }

        return is_array($result) ? sanitize_key((string) ($result['subdomain'] ?? '')) : '';
    }

    /**
     * Step 1: register the full manifest for this deploy.
     *
     * @param string                                     $worker   Worker name.
     * @param array<string,array{hash:string,size:int}>  $manifest '/path' => {hash, size}.
     * @return array{jwt:string,buckets:array<int,array<int,string>>}
     * @throws \RuntimeException On failure.
     */
    public function start_session(string $worker, array $manifest): array {
        $this->assert_worker($worker);

        $result = $this->request(
            'POST',
            '/accounts/' . $this->account . '/workers/scripts/' . $worker . '/assets-upload-session',
            [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => (string) wp_json_encode(['manifest' => (object) $manifest]),
                'timeout' => 120,
            ],
            $this->token
        );

        $jwt = is_array($result) ? (string) ($result['jwt'] ?? '') : '';
        if ($jwt === '') {
            throw new \RuntimeException('Cloudflare did not return an upload session.');
        }

        $buckets = [];
        foreach ((array) ($result['buckets'] ?? []) as $bucket) {
            $buckets[] = array_values(array_map('strval', (array) $bucket));
        }

        return ['jwt' => $jwt, 'buckets' => $buckets];
    }

    /**
     * Step 2: upload one bucket of files.
     *
     * @param string                                         $upload_jwt Session JWT from start_session().
     * @param array<string,array{path:string,mime:string}>   $files      hash => local file + MIME type to serve it with.
     * @return string The completion JWT when this was the last outstanding
     *                bucket, else ''.
     * @throws \RuntimeException On failure (CloudflareAuthException when the JWT expired).
     */
    public function upload_bucket(string $upload_jwt, array $files): string {
        $boundary = 'bs-' . bin2hex(random_bytes(12));
        $body     = '';
        foreach ($files as $hash => $file) {
            $bytes = file_get_contents($file['path']);
            if ($bytes === false) {
                throw new \RuntimeException('Could not read a staged file for upload.');
            }
            // Part name AND filename are the content hash (matches wrangler).
            $body .= "--{$boundary}\r\n"
                . 'Content-Disposition: form-data; name="' . $hash . '"; filename="' . $hash . "\"\r\n"
                . 'Content-Type: ' . $file['mime'] . "\r\n\r\n"
                . base64_encode($bytes) . "\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        $result = $this->request(
            'POST',
            '/accounts/' . $this->account . '/workers/assets/upload?base64=true',
            [
                'headers' => ['Content-Type' => 'multipart/form-data; boundary=' . $boundary],
                'body'    => $body,
                'timeout' => 300,
            ],
            $upload_jwt
        );

        return is_array($result) ? (string) ($result['jwt'] ?? '') : '';
    }

    /**
     * Step 3: publish the uploaded asset set as the Worker's live version.
     * Creates the Worker on first deploy.
     *
     * @param string              $worker         Worker name.
     * @param string              $completion_jwt Completion JWT (or the session JWT when nothing needed uploading).
     * @param array<string,mixed> $config         `assets.config` (html_handling, not_found_handling, _headers, _redirects).
     * @throws \RuntimeException On failure.
     */
    public function publish(string $worker, string $completion_jwt, array $config): void {
        $this->assert_worker($worker);

        $metadata = [
            'assets'             => ['jwt' => $completion_jwt, 'config' => (object) $config],
            'compatibility_date' => self::COMPATIBILITY_DATE,
        ];

        $boundary = 'bs-' . bin2hex(random_bytes(12));
        $body     = "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"metadata\"\r\n"
            . "Content-Type: application/json\r\n\r\n"
            . wp_json_encode($metadata) . "\r\n"
            . "--{$boundary}--\r\n";

        $this->request(
            'PUT',
            '/accounts/' . $this->account . '/workers/scripts/' . $worker,
            [
                'headers' => ['Content-Type' => 'multipart/form-data; boundary=' . $boundary],
                'body'    => $body,
                'timeout' => 120,
            ],
            $this->token
        );
    }

    /**
     * Turn on the Worker's `<worker>.<sub>.workers.dev` address.
     *
     * @param string $worker Worker name.
     * @throws \RuntimeException On failure.
     */
    public function enable_workers_dev(string $worker): void {
        $this->assert_worker($worker);

        $this->request(
            'POST',
            '/accounts/' . $this->account . '/workers/scripts/' . $worker . '/subdomain',
            [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => (string) wp_json_encode(['enabled' => true]),
            ],
            $this->token
        );
    }

    /**
     * Refuse a Worker name that could escape its path segment.
     *
     * @param string $worker Worker name.
     * @throws \RuntimeException When invalid.
     */
    private function assert_worker(string $worker): void {
        if (!self::valid_worker($worker)) {
            throw new \RuntimeException('Invalid Worker name — use lowercase letters, numbers and dashes.');
        }
    }

    /**
     * Send one API request and unwrap Cloudflare's `{success, errors, result}`
     * envelope. Retries transport errors, 429 (honouring Retry-After, capped)
     * and 5xx.
     *
     * @param string              $method HTTP method.
     * @param string              $path   Path under /client/v4.
     * @param array<string,mixed> $args   wp_remote_request args (headers/body/timeout).
     * @param string              $bearer API token or upload JWT.
     * @return mixed The `result` member.
     * @throws \RuntimeException On failure.
     */
    private function request(string $method, string $path, array $args, string $bearer) {
        if (!self::valid_account($this->account)) {
            throw new CloudflareAuthException('Enter a valid Cloudflare account ID (32 characters, found on the Workers & Pages overview).');
        }
        if ($bearer === '') {
            throw new CloudflareAuthException('Enter a Cloudflare API token.');
        }

        $args['method']      = $method;
        $args['timeout']     = $args['timeout'] ?? 30;
        $args['sslverify']   = true;
        $args['redirection'] = 0;
        $args['headers']     = ($args['headers'] ?? []) + [
            'Authorization' => 'Bearer ' . $bearer,
            'Accept'        => 'application/json',
        ];

        $last = null;
        for ($attempt = 1; $attempt <= self::TRIES; $attempt++) {
            $response = wp_remote_request(self::API . $path, $args);

            if (is_wp_error($response)) {
                $last = new \RuntimeException('Could not reach the Cloudflare API: ' . $response->get_error_message());
                $this->pause($attempt, 0);
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $data = json_decode((string) wp_remote_retrieve_body($response), true);

            if ($code >= 200 && $code < 300 && is_array($data) && !empty($data['success'])) {
                return $data['result'] ?? null;
            }

            $error = self::error_text($data);

            if ($code === 429 || $code >= 500) {
                $last = new \RuntimeException(sprintf('Cloudflare API error (HTTP %d%s).', $code, $error !== '' ? ': ' . $error : ''));
                $this->pause($attempt, (int) wp_remote_retrieve_header($response, 'retry-after'));
                continue;
            }
            if ($code === 401 || $code === 403) {
                throw new CloudflareAuthException(sprintf(
                    'Cloudflare refused the request (HTTP %d%s). Check the account ID, and that the API token has the Account → Workers Scripts → Edit permission for that account.',
                    $code,
                    $error !== '' ? ': ' . $error : ''
                ));
            }
            if ($code === 404) {
                throw new CloudflareNotFoundException(sprintf('Cloudflare API: not found%s.', $error !== '' ? ' (' . $error . ')' : ''));
            }

            throw new \RuntimeException(sprintf('Cloudflare API error (HTTP %d%s).', $code, $error !== '' ? ': ' . $error : ''));
        }

        throw $last ?? new \RuntimeException('Cloudflare API request failed.');
    }

    /**
     * Back off before a retry (not after the final attempt).
     *
     * @param int $attempt     Attempt just made (1-based).
     * @param int $retry_after Server-suggested seconds (0 = none).
     */
    private function pause(int $attempt, int $retry_after): void {
        if ($attempt >= self::TRIES) {
            return;
        }
        sleep(min(10, max($retry_after, $attempt * 2)));
    }

    /**
     * First error message from a Cloudflare error envelope, sanitised.
     *
     * @param mixed $data Decoded body.
     */
    private static function error_text($data): string {
        if (!is_array($data) || empty($data['errors']) || !is_array($data['errors'])) {
            return '';
        }
        $first = reset($data['errors']);
        if (!is_array($first)) {
            return '';
        }
        $text = sanitize_text_field((string) ($first['message'] ?? ''));
        $code = (int) ($first['code'] ?? 0);

        return $code > 0 ? sprintf('%s [%d]', $text, $code) : $text;
    }
}
