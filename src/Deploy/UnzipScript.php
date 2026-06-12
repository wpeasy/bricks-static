<?php
/**
 * Generates the one-shot server-side deploy helper.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Deploy;

defined('ABSPATH') || exit;

/**
 * Builds the throwaway PHP script that the plugin uploads to a destination to
 * extract a deploy package locally (turning ~hundreds of slow per-file FTP
 * transfers into one zip upload). It is deliberately hardened:
 *
 *  - Runs only for a caller presenting the exact random token it was built with
 *    (the token is also in the filename, so it isn't even guessable in the URL).
 *  - Refuses to run after its short expiry.
 *  - Guards every zip entry and delete path against escaping the docroot
 *    (zip-slip / path traversal).
 *  - Deletes the package AND itself after an authorised run; the plugin also
 *    removes both over FTP as a backstop.
 */
final class UnzipScript {

    /**
     * Render the deploy helper PHP source.
     *
     * @param string             $token        Random authorisation token (also used in the filename).
     * @param string             $zip_name     Basename of the uploaded package (same directory).
     * @param int                $expires      Unix timestamp after which the script refuses to run.
     * @param array<int,string>  $compressible Extensions to gzip server-side (no '.'), matching Compressor.
     */
    public static function generate(string $token, string $zip_name, int $expires, array $compressible = []): string {
        $token_php = var_export($token, true);
        $zip_php   = var_export($zip_name, true);
        $exp_php   = var_export($expires, true);
        $gz_php    = var_export(array_values(array_map('strval', $compressible)), true);

        return <<<PHP
<?php
// Bricks Static — one-shot deploy helper. Safe to delete.
declare(strict_types=1);

\$TOKEN   = {$token_php};
\$ZIPNAME = {$zip_php};
\$EXPIRES = {$exp_php};
\$GZIP    = {$gz_php};

\$base = __DIR__;
\$zip_path = \$base . '/' . \$ZIPNAME;

header('Content-Type: application/json');

\$cleanup = static function () use (\$zip_path) {
    @unlink(\$zip_path);
    @unlink(__FILE__);
};

\$provided = isset(\$_POST['token']) ? \$_POST['token'] : (isset(\$_GET['token']) ? \$_GET['token'] : '');
if (!is_string(\$provided) || !hash_equals(\$TOKEN, \$provided)) {
    // Do NOT self-delete on a bad token — the plugin cleans up over FTP.
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'forbidden'));
    exit;
}

if (time() > \$EXPIRES) {
    \$cleanup();
    http_response_code(410);
    echo json_encode(array('ok' => false, 'error' => 'expired'));
    exit;
}

\$safe = static function (\$rel) {
    \$rel = str_replace('\\\\', '/', (string) \$rel);
    if (\$rel === '' || \$rel[0] === '/' || strpos(\$rel, '../') !== false || strpos(\$rel, ':') !== false) {
        return null;
    }
    return ltrim(\$rel, '/');
};

\$out = array('ok' => true, 'extracted' => 0, 'deleted' => 0, 'errors' => array());

if (!class_exists('ZipArchive')) {
    \$cleanup();
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => 'ZipArchive unavailable'));
    exit;
}

\$zip = new ZipArchive();
if (\$zip->open(\$zip_path) !== true) {
    \$cleanup();
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => 'cannot open package'));
    exit;
}

for (\$i = 0; \$i < \$zip->numFiles; \$i++) {
    \$name = \$zip->getNameIndex(\$i);
    if (\$name === false || substr(\$name, -1) === '/') {
        continue;
    }
    \$rel = \$safe(\$name);
    if (\$rel === null) {
        \$out['errors'][] = 'unsafe:' . \$name;
        continue;
    }
    \$dest = \$base . '/' . \$rel;
    \$dir  = dirname(\$dest);
    if (!is_dir(\$dir)) {
        @mkdir(\$dir, 0755, true);
    }
    \$src = \$zip->getStream(\$name);
    if (\$src === false) {
        \$out['errors'][] = 'read:' . \$rel;
        continue;
    }
    \$dst = @fopen(\$dest, 'wb');
    if (\$dst === false) {
        fclose(\$src);
        \$out['errors'][] = 'write:' . \$rel;
        continue;
    }
    stream_copy_to_stream(\$src, \$dst);
    fclose(\$dst);
    fclose(\$src);
    \$out['extracted']++;

    // Regenerate the gzip sibling locally (cheaper than transferring it).
    \$ext = strtolower(pathinfo(\$rel, PATHINFO_EXTENSION));
    if (in_array(\$ext, \$GZIP, true) && function_exists('gzencode')) {
        \$data = @file_get_contents(\$dest);
        if (\$data !== false) {
            \$gz = @gzencode(\$data, 9);
            if (\$gz !== false) {
                @file_put_contents(\$dest . '.gz', \$gz);
            }
        }
    }
}
\$zip->close();

\$deletes = isset(\$_POST['deletes']) ? json_decode((string) \$_POST['deletes'], true) : array();
if (is_array(\$deletes)) {
    foreach (\$deletes as \$del) {
        \$rel = \$safe(\$del);
        if (\$rel === null) {
            continue;
        }
        \$path = \$base . '/' . \$rel;
        if (is_file(\$path) && @unlink(\$path)) {
            \$out['deleted']++;
        }
        if (is_file(\$path . '.gz')) {
            @unlink(\$path . '.gz');
        }
    }
}

\$cleanup();
echo json_encode(\$out);
PHP;
    }
}
