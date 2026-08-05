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
     * Content types for the pre-compressed extensions, by extension. Any
     * compressible extension WITHOUT a type here would be served as an
     * undecodable .gz blob, so every Compressor extension must be covered.
     *
     * @return array<string,string>
     */
    private static function content_types(): array {
        return [
            'html' => 'text/html; charset=UTF-8',
            'htm'  => 'text/html; charset=UTF-8',
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'mjs'  => 'application/javascript',
            'svg'  => 'image/svg+xml',
            'json' => 'application/json',
            'map'  => 'application/json',
            'xml'  => 'application/xml',
            'txt'  => 'text/plain; charset=UTF-8',
        ];
    }

    /**
     * FilesMatch blocks that give every pre-compressed sibling the right
     * Content-Encoding + Content-Type. Built from the same extension list
     * Compressor gzips, so the two can't drift (a gzipped type with no header
     * here would serve as a broken binary blob — the sitemap/robots case).
     */
    private static function precompressed_headers(): string {
        $types  = self::content_types();
        $blocks = [];
        foreach (Compressor::extensions() as $ext) {
            if (!isset($types[$ext])) {
                continue;
            }
            $blocks[] = "    <FilesMatch \"\\.{$ext}\\.gz\$\">\n"
                . "        Header set Content-Encoding gzip\n"
                . "        Header set Content-Type \"{$types[$ext]}\"\n"
                . "        Header append Vary Accept-Encoding\n"
                . '    </FilesMatch>';
        }

        return implode("\n", $blocks);
    }

    /**
     * The rule body (without markers). The pre-compressed gzip rules are emitted
     * only when the host can actually produce .gz siblings.
     */
    private static function rules(): string {
        $assets = self::ASSET_EXT;

        $rewrite = '';
        if (Compressor::available()) {
            $rewrite = <<<HTACCESS


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
HTACCESS;
        }

        // Pre-compressed Content-Encoding/Type headers. Without them a .gz
        // sibling would be served as an undecodable binary blob.
        $precompressed = Compressor::available() ? "\n" . self::precompressed_headers() . "\n" : '';

        return <<<HTACCESS
DirectoryIndex index.html
$rewrite

<IfModule mod_headers.c>
$precompressed
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

        // Omit the gzip_static directive when the host can't produce .gz siblings.
        $gzip = Compressor::available()
            ? "\n# Serve pre-compressed .gz when available.\ngzip_static on;\n"
            : '';

        return <<<NGINX
# Bricks Static — nginx equivalent. Paste inside your server { } block, then reload nginx.
# (nginx config cannot be applied over FTP/SFTP, so this is manual.)

index index.html;
$gzip
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
