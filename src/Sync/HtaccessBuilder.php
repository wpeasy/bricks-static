<?php
/**
 * Destination server config (.htaccess + nginx snippet).
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Sync;

defined('ABSPATH') || exit;

/**
 * Generates the destination web-server rules: serve pre-compressed `.gz` when
 * the client supports it, and apply sensible cache-control. Produces an Apache
 * `.htaccess` (uploaded automatically) and an equivalent nginx snippet (shown
 * for manual paste, since nginx config can't be applied over FTP/SFTP).
 */
final class HtaccessBuilder {

    /**
     * Long-cache asset extensions (immutable).
     */
    private const ASSET_EXT = 'css|js|mjs|jpg|jpeg|png|gif|webp|avif|svg|ico|woff|woff2|ttf|otf|eot|mp4|webm|ogg|mp3';

    /**
     * Marker name for our managed block.
     */
    private const MARKER = 'Bricks Static';

    /**
     * Merge our rules into an existing .htaccess: strip any previous Bricks
     * Static block and the WordPress rewrite block (which conflicts with static
     * serving), then prepend our fresh block. All other custom rules are kept.
     *
     * @param string $existing Current remote .htaccess contents ('' if none).
     */
    public static function merge(string $existing): string {
        $existing = self::strip_block($existing, self::MARKER);
        $existing = self::strip_block($existing, 'WordPress');
        $existing = trim($existing);

        $block = self::block();

        return $existing === '' ? $block . "\n" : $block . "\n\n" . $existing . "\n";
    }

    /**
     * Our managed block, wrapped in BEGIN/END markers for idempotent merging.
     */
    public static function block(): string {
        return "# BEGIN " . self::MARKER . "\n" . self::rules() . "\n# END " . self::MARKER . "\n";
    }

    /**
     * Build the standalone Apache .htaccess contents (our block only).
     */
    public static function htaccess(): string {
        return self::block();
    }

    /**
     * Remove a "# BEGIN Name … # END Name" block from content.
     *
     * @param string $content Content.
     * @param string $name    Marker name.
     */
    private static function strip_block(string $content, string $name): string {
        $quoted  = preg_quote($name, '/');
        $pattern = '/\n*# BEGIN ' . $quoted . '\b.*?# END ' . $quoted . '[^\n]*\n?/is';

        return (string) (preg_replace($pattern, "\n", $content) ?? $content);
    }

    /**
     * The rule body (without markers).
     */
    private static function rules(): string {
        $assets = self::ASSET_EXT;

        return <<<HTACCESS
DirectoryIndex index.html

<IfModule mod_rewrite.c>
    RewriteEngine On

    # Serve a pre-compressed sibling (file.gz) when the client accepts gzip.
    RewriteCond %{HTTP:Accept-Encoding} gzip
    RewriteCond %{REQUEST_FILENAME}\.gz -f
    RewriteRule ^(.*)\$ \$1.gz [L]

    # Same, for directory requests resolved to index.html.
    RewriteCond %{HTTP:Accept-Encoding} gzip
    RewriteCond %{REQUEST_FILENAME}index.html.gz -f
    RewriteRule ^(.*)/?\$ \$1/index.html.gz [L]
</IfModule>

<IfModule mod_headers.c>
    # Pre-compressed responses: set encoding + correct content type, vary on encoding.
    <FilesMatch "\.html\.gz\$">
        Header set Content-Encoding gzip
        Header set Content-Type "text/html; charset=UTF-8"
        Header append Vary Accept-Encoding
    </FilesMatch>
    <FilesMatch "\.css\.gz\$">
        Header set Content-Encoding gzip
        Header set Content-Type "text/css"
        Header append Vary Accept-Encoding
    </FilesMatch>
    <FilesMatch "\.js\.gz\$">
        Header set Content-Encoding gzip
        Header set Content-Type "application/javascript"
        Header append Vary Accept-Encoding
    </FilesMatch>

    # Cache policy: assets immutable for a year, HTML always revalidated.
    <FilesMatch "\.($assets)\$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>
    <FilesMatch "\.html\$">
        Header set Cache-Control "public, max-age=0, must-revalidate"
    </FilesMatch>
</IfModule>
HTACCESS;
    }

    /**
     * Build an equivalent nginx server-block snippet (for manual paste).
     */
    public static function nginx(): string {
        $assets = self::ASSET_EXT;

        return <<<NGINX
# Bricks Static — nginx equivalent. Paste inside your server { } block, then reload nginx.
# (nginx config cannot be applied over FTP/SFTP, so this is manual.)

index index.html;

# Serve pre-compressed .gz when available.
gzip_static on;

location / {
    try_files \$uri \$uri/ \$uri/index.html =404;
}

# Assets: immutable for a year.
location ~* \.($assets)\$ {
    add_header Cache-Control "public, max-age=31536000, immutable";
}

# HTML: always revalidate.
location ~* \.html\$ {
    add_header Cache-Control "public, max-age=0, must-revalidate";
}
NGINX;
    }
}
