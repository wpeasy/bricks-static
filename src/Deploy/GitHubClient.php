<?php
/**
 * GitHub REST API client (Git Data + Pages).
 *
 * @package WPEasy\ShipStatic
 * @since   2.1.3
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Deploy;

defined('ABSPATH') || exit;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception messages are plain text for JSON responses (rendered as text by the dashboard) and WP-CLI, never echoed into HTML; esc_html() here would show entities to the user.

/**
 * Speaks the GitHub REST API for a GitHub Pages deploy — see
 * `docs/GITHUB_PAGES_PLAN.md` §4 for the flow: read the branch (ref → commit →
 * recursive tree), upload changed binaries as blobs, build the new tree in
 * batches (text files inline), commit, then move the branch fast-forward only.
 *
 * Every request goes to the fixed host `api.github.com` — never a
 * user-supplied URL — so the `UrlSafety` SSRF guard doesn't apply; owner,
 * repo and branch are validated to strict character sets by `Settings\Schema`
 * and again here, and each path segment is URL-encoded. TLS is always
 * verified and redirects are never followed (a renamed repository answers
 * 301; following it would send the token on), per SECURITY_PATTERNS.md §3d.
 * The token is never logged or included in an exception message.
 */
final class GitHubClient {

    /**
     * API base. Fixed — never derived from settings.
     */
    private const API = 'https://api.github.com';

    /**
     * REST API version pinned for every request (verified in the spike,
     * docs/GITHUB_PAGES_PLAN.md §13).
     */
    private const API_VERSION = '2026-03-10';

    /**
     * Attempts per request on a transport error or 5xx.
     */
    private const TRIES = 3;

    private string $owner;

    private string $repo;

    private string $token;

    /**
     * @param string $owner Account or organisation that owns the repository.
     * @param string $repo  Repository name.
     * @param string $token Fine-grained personal access token.
     */
    public function __construct(string $owner, string $repo, string $token) {
        $this->owner = trim($owner);
        $this->repo  = trim($repo);
        $this->token = trim($token);
    }

    /**
     * Whether owner, repo and token are present and well-formed.
     */
    public function configured(): bool {
        return self::valid_owner($this->owner) && self::valid_repo($this->repo) && $this->token !== '';
    }

    /**
     * Whether a string is a GitHub account/organisation name.
     *
     * @param string $owner Candidate.
     */
    public static function valid_owner(string $owner): bool {
        return (bool) preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,38})$/', $owner);
    }

    /**
     * Whether a string is a GitHub repository name.
     *
     * @param string $repo Candidate.
     */
    public static function valid_repo(string $repo): bool {
        return $repo !== '.' && $repo !== '..' && (bool) preg_match('/^[A-Za-z0-9._-]{1,100}$/', $repo);
    }

    /**
     * Whether a string is a branch name we accept: a conservative subset of
     * git's ref-name rules.
     *
     * @param string $branch Candidate.
     */
    public static function valid_branch(string $branch): bool {
        return (bool) preg_match('/^[A-Za-z0-9_][A-Za-z0-9._\/-]{0,99}$/', $branch)
            && strpos($branch, '..') === false
            && strpos($branch, '//') === false
            && substr($branch, -1) !== '/'
            && substr($branch, -1) !== '.'
            && !str_ends_with($branch, '.lock');
    }

    /**
     * The repository (also the connection test: fails on a bad token, a
     * token without access to it, or a missing repository).
     *
     * @return array<string,mixed>
     * @throws \RuntimeException On failure.
     */
    public function repository(): array {
        try {
            return (array) $this->request('GET', '');
        } catch (GitHubNotFoundException $e) {
            throw new GitHubAuthException(sprintf(
                'GitHub repository %s/%s was not found — check the owner and repository names, and that the token was given access to this repository.',
                $this->owner,
                $this->repo
            ));
        }
    }

    /**
     * Whether the token can write to the repository: creates one empty blob
     * (harmless — nothing references it). The repository's `permissions`
     * field can't answer this: it reports the account's role, not the
     * token's grants.
     *
     * @throws \RuntimeException On failure other than a missing permission.
     */
    public function can_write(): bool {
        try {
            $this->create_blob('');
            return true;
        } catch (GitHubAuthException $e) {
            return false;
        }
    }

    /**
     * The repository's GitHub Pages site, or null when Pages is off. Throws
     * GitHubAuthException when the token lacks Pages: Read (callers treat
     * that as "unknown", not as an error).
     *
     * @return array<string,mixed>|null
     */
    public function pages_site(): ?array {
        try {
            return (array) $this->request('GET', '/pages');
        } catch (GitHubNotFoundException $e) {
            return null;
        }
    }

    /**
     * The Pages build for a commit (newest first, among recent builds), or
     * null when there is none yet. Older commits' builds can read `errored`
     * when a newer push cancelled them — only ask about the commit you made.
     *
     * @param string $commit Commit SHA.
     * @return array<string,mixed>|null
     */
    public function pages_build_for(string $commit): ?array {
        $builds = (array) $this->request('GET', '/pages/builds?per_page=10');
        foreach ($builds as $build) {
            if (is_array($build) && (string) ($build['commit'] ?? '') === $commit) {
                return $build;
            }
        }

        return null;
    }

    /**
     * The commit SHA a branch points at, or null when the branch doesn't
     * exist yet.
     *
     * @param string $branch Branch name.
     */
    public function branch_head(string $branch): ?string {
        try {
            $ref = (array) $this->request('GET', '/git/ref/heads/' . self::ref_path($branch));
        } catch (GitHubNotFoundException $e) {
            return null;
        }

        return self::sha((string) ($ref['object']['sha'] ?? ''));
    }

    /**
     * The tree SHA of a commit.
     *
     * @param string $commit Commit SHA.
     */
    public function commit_tree(string $commit): string {
        $data = (array) $this->request('GET', '/git/commits/' . self::sha($commit));
        $tree = self::sha((string) ($data['tree']['sha'] ?? ''));
        if ($tree === '') {
            throw new \RuntimeException('GitHub returned a commit without a tree.');
        }

        return $tree;
    }

    /**
     * Every file in a tree: path => blob SHA. Uses one recursive read; when
     * GitHub truncates it (over 100,000 entries or 7 MB), walks the subtrees
     * one level at a time instead.
     *
     * @param string $tree Tree SHA.
     * @return array<string,string>
     */
    public function tree_files(string $tree): array {
        $data = (array) $this->request('GET', '/git/trees/' . self::sha($tree) . '?recursive=1');
        if (empty($data['truncated'])) {
            return self::blobs_of((array) ($data['tree'] ?? []), '');
        }

        $files = [];
        $queue = [['', self::sha($tree)]];
        while ($queue !== []) {
            [$prefix, $sha] = array_shift($queue);
            $level = (array) $this->request('GET', '/git/trees/' . $sha);
            foreach ((array) ($level['tree'] ?? []) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $path = $prefix . (string) ($entry['path'] ?? '');
                if (($entry['type'] ?? '') === 'tree') {
                    $queue[] = [$path . '/', self::sha((string) ($entry['sha'] ?? ''))];
                } elseif (($entry['type'] ?? '') === 'blob') {
                    $files[$path] = (string) ($entry['sha'] ?? '');
                }
            }
        }

        return $files;
    }

    /**
     * Upload one file's bytes as a blob.
     *
     * @param string $bytes File contents.
     * @return string Blob SHA.
     */
    public function create_blob(string $bytes): string {
        $data = (array) $this->request('POST', '/git/blobs', ['content' => base64_encode($bytes), 'encoding' => 'base64']);

        return self::sha((string) ($data['sha'] ?? ''));
    }

    /**
     * Create a tree from entries on top of an optional base tree.
     *
     * @param array<int,array<string,mixed>> $entries {path, mode, type, sha|content}; `sha: null` deletes.
     * @param string|null                    $base    Base tree SHA (null = a tree of only these entries).
     * @return string Tree SHA.
     */
    public function create_tree(array $entries, ?string $base): string {
        $body = ['tree' => $entries];
        if ($base !== null && $base !== '') {
            $body['base_tree'] = self::sha($base);
        }
        $data = (array) $this->request('POST', '/git/trees', $body);

        return self::sha((string) ($data['sha'] ?? ''));
    }

    /**
     * Create a commit.
     *
     * @param string             $message Commit message.
     * @param string             $tree    Tree SHA.
     * @param array<int,string>  $parents Parent commit SHAs (empty = first commit of a new branch).
     * @return string Commit SHA.
     */
    public function create_commit(string $message, string $tree, array $parents): string {
        $data = (array) $this->request('POST', '/git/commits', [
            'message' => $message,
            'tree'    => self::sha($tree),
            'parents' => array_values(array_map([self::class, 'sha'], $parents)),
        ]);

        return self::sha((string) ($data['sha'] ?? ''));
    }

    /**
     * Point a branch at a commit: creates the branch when $create, otherwise
     * moves it FAST-FORWARD ONLY (never forced, so nobody's commits are
     * discarded).
     *
     * @param string $branch Branch name.
     * @param string $commit Commit SHA.
     * @param bool   $create Whether the branch is new.
     * @throws GitHubConflictException When the branch moved since it was read.
     */
    public function set_branch(string $branch, string $commit, bool $create): void {
        if ($create) {
            try {
                $this->request('POST', '/git/refs', ['ref' => 'refs/heads/' . $branch, 'sha' => self::sha($commit)]);
            } catch (GitHubValidationException $e) {
                // "Reference already exists": someone created it meanwhile.
                throw new GitHubConflictException('The branch was created by someone else while publishing.');
            }
            return;
        }

        try {
            $this->request('PATCH', '/git/refs/heads/' . self::ref_path($branch), ['sha' => self::sha($commit), 'force' => false]);
        } catch (GitHubValidationException $e) {
            throw new GitHubConflictException('The branch changed while publishing (someone pushed to it).');
        }
    }

    /**
     * Send one request; return the decoded body. Retries transport errors
     * and 5xx; maps 401/403/404/409/422/429 and 301 to typed exceptions.
     *
     * @param string                    $method HTTP method.
     * @param string                    $path   Path under /repos/{owner}/{repo} ('' = the repository).
     * @param array<string,mixed>|null  $body   JSON body.
     * @return mixed Decoded JSON.
     * @throws \RuntimeException On failure.
     */
    private function request(string $method, string $path, ?array $body = null) {
        if (!self::valid_owner($this->owner) || !self::valid_repo($this->repo)) {
            throw new GitHubAuthException('Enter the GitHub owner and repository for this destination.');
        }
        if ($this->token === '') {
            throw new GitHubAuthException('Enter a GitHub access token.');
        }

        $url  = self::API . '/repos/' . rawurlencode($this->owner) . '/' . rawurlencode($this->repo) . $path;
        $args = [
            'method'      => $method,
            'timeout'     => 60,
            'sslverify'   => true,
            'redirection' => 0,
            'headers'     => [
                'Authorization'        => 'Bearer ' . $this->token,
                'Accept'               => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => self::API_VERSION,
                'User-Agent'           => 'ShipStatic/' . (defined('SHIPSTATIC_VERSION') ? SHIPSTATIC_VERSION : '0'),
            ],
        ];
        if ($body !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body']                    = (string) wp_json_encode($body);
        }

        $last = null;
        for ($attempt = 1; $attempt <= self::TRIES; $attempt++) {
            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                $last = new \RuntimeException('Could not reach the GitHub API: ' . $response->get_error_message());
                $this->pause($attempt);
                continue;
            }

            $code  = (int) wp_remote_retrieve_response_code($response);
            $data  = json_decode((string) wp_remote_retrieve_body($response), true);
            $error = self::error_text($data);

            if ($code >= 200 && $code < 300) {
                return $data;
            }
            if ($code >= 500) {
                $last = new \RuntimeException(sprintf('GitHub API error (HTTP %d%s).', $code, $error !== '' ? ': ' . $error : ''));
                $this->pause($attempt);
                continue;
            }

            $wait = self::rate_limit_wait($response, $code, $error);
            if ($wait > 0) {
                throw new GitHubRateLimitException('GitHub’s API rate limit was reached.', $wait);
            }
            if ($code === 301 || $code === 302 || $code === 307) {
                throw new GitHubAuthException(sprintf(
                    'GitHub says this repository has moved (renamed or transferred). Update the owner and repository for this destination — the current address is %s.',
                    esc_url_raw((string) wp_remote_retrieve_header($response, 'location'))
                ));
            }
            if ($code === 401 || $code === 403) {
                throw new GitHubAuthException(sprintf(
                    'GitHub refused the request (HTTP %d%s). Check that the token has not expired, was given access to %s/%s, and has the Contents: Read and write permission.',
                    $code,
                    $error !== '' ? ': ' . $error : '',
                    $this->owner,
                    $this->repo
                ));
            }
            if ($code === 404) {
                throw new GitHubNotFoundException(sprintf('GitHub API: not found%s.', $error !== '' ? ' (' . $error . ')' : ''));
            }
            if ($code === 409 && stripos($error, 'empty') !== false) {
                throw new GitHubAuthException('The GitHub repository is empty. Add any first commit (for example a README) on GitHub, then sync again.');
            }
            if ($code === 422 || $code === 409) {
                throw new GitHubValidationException(sprintf('GitHub rejected the request (HTTP %d%s).', $code, $error !== '' ? ': ' . $error : ''));
            }

            throw new \RuntimeException(sprintf('GitHub API error (HTTP %d%s).', $code, $error !== '' ? ': ' . $error : ''));
        }

        throw $last ?? new \RuntimeException('GitHub API request failed.');
    }

    /**
     * When a 403/429 is a rate limit, the Unix time to resume at; 0 otherwise.
     * Secondary limits send `retry-after`; the primary limit sends
     * `x-ratelimit-remaining: 0` + `x-ratelimit-reset`.
     *
     * @param array|\WP_Error $response Response.
     * @param int             $code     HTTP status.
     * @param string          $error    GitHub's message.
     */
    private static function rate_limit_wait($response, int $code, string $error): int {
        if ($code !== 403 && $code !== 429) {
            return 0;
        }
        $retry = (int) wp_remote_retrieve_header($response, 'retry-after');
        if ($retry > 0) {
            return time() + $retry;
        }
        if ((string) wp_remote_retrieve_header($response, 'x-ratelimit-remaining') === '0') {
            $reset = (int) wp_remote_retrieve_header($response, 'x-ratelimit-reset');
            return $reset > time() ? $reset + 1 : time() + 60;
        }
        if ($code === 429 || stripos($error, 'rate limit') !== false) {
            return time() + 60; // GitHub's advice when no header says how long.
        }

        return 0;
    }

    /**
     * Back off before a retry (not after the final attempt).
     *
     * @param int $attempt Attempt just made (1-based).
     */
    private function pause(int $attempt): void {
        if ($attempt < self::TRIES) {
            sleep($attempt * 2);
        }
    }

    /**
     * A branch name as a URL path (each segment encoded, slashes kept).
     *
     * @param string $branch Branch name.
     */
    private static function ref_path(string $branch): string {
        if (!self::valid_branch($branch)) {
            throw new \RuntimeException('Enter a valid branch name for this destination.');
        }

        return implode('/', array_map('rawurlencode', explode('/', $branch)));
    }

    /**
     * A 40-hex object id, or '' when the value isn't one (so a malformed API
     * answer can never be spliced into a path).
     *
     * @param string $sha Candidate.
     */
    private static function sha(string $sha): string {
        $sha = strtolower(trim($sha));

        return preg_match('/^[a-f0-9]{40}$/', $sha) ? $sha : '';
    }

    /**
     * Blob entries of a recursive tree listing: path => SHA.
     *
     * @param array<int,mixed> $entries Tree entries.
     * @param string           $prefix  Path prefix.
     * @return array<string,string>
     */
    private static function blobs_of(array $entries, string $prefix): array {
        $files = [];
        foreach ($entries as $entry) {
            if (is_array($entry) && ($entry['type'] ?? '') === 'blob') {
                $files[$prefix . (string) ($entry['path'] ?? '')] = (string) ($entry['sha'] ?? '');
            }
        }

        return $files;
    }

    /**
     * GitHub's error message, sanitised.
     *
     * @param mixed $data Decoded body.
     */
    private static function error_text($data): string {
        if (!is_array($data)) {
            return '';
        }

        return sanitize_text_field((string) ($data['message'] ?? ''));
    }
}
