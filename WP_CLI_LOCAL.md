# WP-CLI on Windows + Local by Flywheel

How to run WP-CLI from any terminal (Git Bash, PowerShell-via-bash, VS Code terminal, Claude Code) without using Local's "Open Site Shell" each time.

## The problem

Local by Flywheel ships with WP-CLI bundled per-site, but only exposes it inside Local's "Open Site Shell" terminal. Outside that shell, plain `wp` is not on PATH, and you can't just install WP-CLI globally because the bundled PHP has the right extensions and config to match each site.

## What we'll set up

- A bash wrapper script `~/bin/wp` that auto-detects Local's bundled PHP + auto-picks the right per-site php.ini so DB commands connect on the correct port.
- A minimal `~/bin/wp-cli.ini` (fallback for when you're not inside a Local site) that loads the PHP extensions WP-CLI needs.
- `~/bin` added to `$PATH` so plain `wp <command>` works in every new shell.

After this, you can run `wp option get …`, `wp post list`, `wp i18n make-pot …`, `wp plugin list`, etc. from any directory in any WordPress install on your machine — **DB commands included**, with no per-site shell switching.

> **If you've been told to use this doc and `wp` is throwing `mysqli_real_connect()… target machine actively refused it`** — your wrapper is the OLD non-auto-detecting one. Replace `~/bin/wp` with the script in [Step 3](#step-3--create-the-wrapper-script) below. That single replacement fixes the DB-connection error permanently. The auto-detect logic is documented in the wrapper's header comment.

## One-time setup

### Step 1 — Find Local's bundled PHP and wp-cli.phar

Local installs to `C:\Program Files (x86)\Local\` (32-bit installer path even on 64-bit Windows). The relevant pieces:

```
C:\Program Files (x86)\Local\resources\extraResources\bin\wp-cli\wp-cli.phar
C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-<VERSION>\bin\win64\php.exe
C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-<VERSION>\bin\win64\ext\
```

To list PHP versions Local has installed:

```bash
ls "/c/Program Files (x86)/Local/resources/extraResources/lightning-services/" | grep -i php
```

To check which PHP version a specific Local site uses, look at:

```
C:\Users\<you>\Local Sites\<site-name>\conf\php\
```

The folder name there gives the PHP version (e.g. `php-8.2.29+0`). Use that version's binary in the wrapper below.

> **Tip:** If your sites use multiple PHP versions, pick the newest version installed by Local for the wrapper — WP-CLI itself is PHP-agnostic and most commands don't care which site's PHP you're using.

### Step 2 — Create the PHP ini override

WP-CLI needs `mbstring` (mandatory for `i18n` commands) and a few other extensions that Local's CLI PHP doesn't load by default. We supply them via a custom ini.

Create `~/bin/wp-cli.ini`:

```ini
; Minimal php.ini for running WP-CLI against Local by Flywheel's bundled PHP.
; Loaded via `php -c`. Path lives next to the wp wrapper script.

extension_dir = "C:\Program Files (x86)\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\ext"

; Extensions WP-CLI needs (mbstring is the hard requirement for `i18n make-pot`).
extension = php_mbstring.dll
extension = php_openssl.dll
extension = php_curl.dll
extension = php_mysqli.dll
extension = php_pdo_mysql.dll
extension = php_fileinfo.dll
extension = php_zip.dll
extension = php_gd.dll
extension = php_sodium.dll
extension = php_intl.dll

; Reasonable runtime limits for long-running CLI tasks.
memory_limit = 512M
date.timezone = UTC
```

> **Update the `extension_dir` path** if your Local PHP version differs. Backslashes are required (Windows-style path inside the ini).

### Step 3 — Create the wrapper script

Create `~/bin/wp` with **two auto-detections**:

1. **PHP_EXE** — picks the highest-numbered PHP version Local has installed (no hardcoded `php-8.2.29+0` path that goes stale on Local upgrades).
2. **PHP_INI** — when run from inside a Local site, walks up looking for `wp-config.php`, matches the site folder against `~/AppData/Roaming/Local/sites.json`, and uses the **rendered per-site php.ini**. That ini has Local's MySQL port override, so DB commands (`wp option get`, `wp post list`, etc.) connect correctly. Falls back to `~/bin/wp-cli.ini` for non-DB commands run outside a site.

Both auto-detections add ~10 ms per invocation — cheap. The alternative (hardcoded paths) breaks every Local upgrade AND breaks every DB command, both with cryptic errors.

```bash
#!/usr/bin/env bash
# WP-CLI wrapper for Local by Flywheel sites on Windows.
#
# Why this exists: Local doesn't expose `wp` on PATH outside its "Open Site
# Shell" terminal. This wrapper points at Local's bundled PHP + wp-cli.phar
# so plain `wp <cmd>` Just Works from any directory in any Local WordPress
# install, in any shell.
#
# Two auto-detections happen on every invocation:
#
#   1. PHP_EXE — picks the highest-numbered PHP version Local has installed,
#      so a Local upgrade that bumps the bundled PHP doesn't break the
#      wrapper (no hardcoded php-8.2.29+0 path).
#
#   2. PHP_INI — if the cwd is inside a Local site (walks up looking for
#      wp-config.php, matches the site folder against ~/AppData/Roaming/Local/
#      sites.json, finds the rendered site php.ini), uses the SITE'S php.ini.
#      That ini has the per-site MySQL port override Local writes, so DB-
#      reading commands like `wp option get`, `wp post list`, etc. connect
#      correctly. Falls back to ~/bin/wp-cli.ini otherwise.
#
# The fallback ini covers non-DB commands (`wp i18n make-pot`, `wp --version`,
# anything that doesn't need a wp_db connection).

# --- PHP_EXE auto-detect (picks newest installed) ---
PHP_DIR=$(ls -d "/c/Program Files (x86)/Local/resources/extraResources/lightning-services"/php-*/ 2>/dev/null | sort -V | tail -1)
PHP_EXE="${PHP_DIR}bin/win64/php.exe"
WP_CLI_PHAR="C:/Program Files (x86)/Local/resources/extraResources/bin/wp-cli/wp-cli.phar"

if [ ! -x "$PHP_EXE" ]; then
    echo "wp: bundled PHP not found." >&2
    echo "wp: looked for php-*/bin/win64/php.exe under" >&2
    echo "wp:   /c/Program Files (x86)/Local/resources/extraResources/lightning-services" >&2
    echo "wp: is Local installed?" >&2
    exit 1
fi
if [ ! -f "$WP_CLI_PHAR" ]; then
    echo "wp: wp-cli.phar not found at $WP_CLI_PHAR" >&2
    echo "wp: is Local's bundled wp-cli present? reinstall Local if not." >&2
    exit 1
fi

# --- PHP_INI auto-detect (per-site, for DB connectivity) ---
# If we're inside a Local site, use that site's rendered php.ini so the
# MySQL port override kicks in. Otherwise fall back to the generic ini
# (which still works for non-DB commands).
detect_site_ini() {
    local cwd="$PWD"
    local site_root=""
    while [ "$cwd" != "/" ] && [ -n "$cwd" ]; do
        if [ -f "$cwd/wp-config.php" ]; then
            site_root="$cwd"
            break
        fi
        cwd=$(dirname "$cwd")
    done
    [ -z "$site_root" ] && return 1

    # site_root looks like .../Local Sites/{folder}/app/public/...
    local site_folder
    site_folder=$(echo "$site_root" | sed -E 's|.*Local Sites/([^/]+)/app/public.*|\1|')
    [ -z "$site_folder" ] && return 1

    local sites_json="$HOME/AppData/Roaming/Local/sites.json"
    [ -f "$sites_json" ] || return 1

    # Match the path field. Local stores paths as ~\\Local Sites\\{folder}
    # (escaped backslashes in the JSON).
    local id
    id=$(grep -oE "\"id\":\"[^\"]+\"[^}]*\"path\":\"[^\"]*\\\\\\\\${site_folder}\"" "$sites_json" \
        | grep -oE '"id":"[^"]+"' | head -1 | grep -oE '"[^"]+"$' | tr -d '"')
    [ -z "$id" ] && return 1

    local site_ini="$HOME/AppData/Roaming/Local/run/${id}/conf/php/php.ini"
    [ -f "$site_ini" ] || return 1
    echo "$site_ini"
}

PHP_INI=$(detect_site_ini || echo "$HOME/bin/wp-cli.ini")

"$PHP_EXE" -c "$PHP_INI" "$WP_CLI_PHAR" "$@"
```

Then make it executable:

```bash
chmod +x ~/bin/wp
```

### Step 4 — Add `~/bin` to PATH

Add this line to `~/.bashrc` (or `~/.bash_profile` if that's what your shell loads):

```bash
export PATH="$HOME/bin:$PATH"
```

One-liner that only adds it if missing:

```bash
grep -q "PATH=.*\$HOME/bin" ~/.bashrc 2>/dev/null \
    || echo 'export PATH="$HOME/bin:$PATH"' >> ~/.bashrc
```

Reload:

```bash
source ~/.bashrc
```

### Step 5 — Verify

```bash
wp --version
# WP-CLI 2.12.0
```

If you get "command not found", `~/bin` isn't on PATH yet — open a new shell or `source ~/.bashrc`.

If you get the mbstring error, check the `extension_dir` path in `wp-cli.ini` matches the PHP version you set in the wrapper.

---

## Common WP-CLI commands

Most commands need to be run from inside a WordPress install (so wp-cli can find `wp-config.php`), or with `--path=<wp-root>`.

### From a plugin directory inside a Local site

```bash
cd "/c/Users/<you>/Local Sites/<site>/app/public/wp-content/plugins/<plugin>"
wp plugin list
wp option get blogname
```

### From outside any WordPress install

```bash
wp --path="/c/Users/<you>/Local Sites/<site>/app/public" plugin list
```

### Skip WordPress bootstrap (for static-analysis commands like i18n)

```bash
wp i18n make-pot . languages/myplugin.pot --domain=myplugin --skip-plugins --skip-themes
```

`--skip-plugins --skip-themes` avoids loading other plugins/themes that might error during CLI bootstrap.

---

## i18n workflow (POT → PO → MO)

Full cycle for keeping translations in sync with source code changes.

### Prerequisite: gettext utilities

`msgmerge` and `msgfmt` come with GNU gettext. On Windows you usually get them with Git for Windows (Git Bash includes them):

```bash
which msgmerge msgfmt
# /usr/bin/msgmerge
# /usr/bin/msgfmt
```

If missing, install Git for Windows or grab gettext binaries separately.

### Step 1 — Regenerate POT from source

```bash
cd /path/to/your/plugin
wp i18n make-pot . languages/myplugin.pot --domain=myplugin --skip-plugins --skip-themes
```

This rebuilds the `.pot` from scratch by scanning all PHP/JS files for `__()`, `_e()`, `esc_html__()`, etc. with your text domain.

Strings that are no longer in the source are silently dropped from the new `.pot`.

### Step 2 — Merge POT into each PO

```bash
cd languages
for po in myplugin-*.po; do
    msgmerge --update --backup=none "$po" myplugin.pot
done
```

What `msgmerge` does:

- **Existing translations preserved** — any `msgid` already translated stays translated.
- **New strings added** — appear with empty `msgstr`, ready for a translator.
- **Removed strings dropped** — strings no longer in the POT are removed (with `--backup=none`).
- **Fuzzy guesses** — when a new `msgid` looks similar to an old removed one, `msgmerge` copies the old translation and marks it `#, fuzzy`. WordPress treats fuzzy entries as untranslated (falls back to English) until a translator confirms or rewrites them.

### Step 3 — Compile PO to MO

WordPress reads `.mo` (binary) at runtime, not `.po` (source).

```bash
cd languages
for po in myplugin-*.po; do
    msgfmt "$po" -o "${po%.po}.mo"
done
```

### Step 4 — Inspect what needs translator attention

```bash
cd languages
for po in myplugin-*.po; do
    untranslated=$(msgattrib --untranslated "$po" 2>/dev/null | grep -c '^msgid ')
    fuzzy=$(msgattrib --only-fuzzy "$po" 2>/dev/null | grep -c '^msgid ')
    echo "$po: $untranslated untranslated, $fuzzy fuzzy"
done
```

Hand the `.po` files to translators, or open them in Poedit / Loco Translate to fill in the blanks.

### One-shot script

Put this in `scripts/update-translations.sh` of any plugin to do all four steps:

```bash
#!/usr/bin/env bash
set -euo pipefail

DOMAIN="myplugin"   # ← change per project
LANG_DIR="languages"

cd "$(dirname "$0")/.."

echo "==> Regenerating POT"
wp i18n make-pot . "$LANG_DIR/${DOMAIN}.pot" --domain="$DOMAIN" --skip-plugins --skip-themes

echo "==> Merging into PO files"
for po in "$LANG_DIR/${DOMAIN}"-*.po; do
    [ -f "$po" ] || continue
    msgmerge --update --backup=none "$po" "$LANG_DIR/${DOMAIN}.pot"
done

echo "==> Compiling MO files"
for po in "$LANG_DIR/${DOMAIN}"-*.po; do
    [ -f "$po" ] || continue
    msgfmt "$po" -o "${po%.po}.mo"
done

echo "==> Translation status"
for po in "$LANG_DIR/${DOMAIN}"-*.po; do
    [ -f "$po" ] || continue
    untranslated=$(msgattrib --untranslated "$po" 2>/dev/null | grep -c '^msgid ' || echo 0)
    fuzzy=$(msgattrib --only-fuzzy "$po" 2>/dev/null | grep -c '^msgid ' || echo 0)
    echo "$po: $untranslated untranslated, $fuzzy fuzzy"
done
```

---

## Gotchas

### Local upgrades PHP

When Local releases an update with a new bundled PHP version, the path `php-8.2.29+0` becomes stale. The wrapper script fails with "bundled PHP not found".

**Fix:** Run `ls "/c/Program Files (x86)/Local/resources/extraResources/lightning-services/" | grep -i php` to find the new version, then update both `~/bin/wp` (the `PHP_EXE` line) and `~/bin/wp-cli.ini` (the `extension_dir` line).

To make the wrapper auto-detect, use a glob in the wrapper (uses the highest-numbered PHP version):

```bash
PHP_DIR=$(ls -d "/c/Program Files (x86)/Local/resources/extraResources/lightning-services"/php-*/ 2>/dev/null | sort -V | tail -1)
PHP_EXE="${PHP_DIR}bin/win64/php.exe"
```

### `unexpected '(' in Unknown on line 7` from PHP

This happens when PHP is given an `-d` flag whose value contains `(` characters — like `-d extension_dir="C:\Program Files (x86)\..."`. The Windows path with parens trips PHP's CLI parser even when properly quoted at the bash level.

**Fix:** Use `php -c <ini-file>` instead of `-d` for any setting whose value contains parentheses. That's why we use `~/bin/wp-cli.ini`.

### Multiple PHP versions across sites

If site A is on PHP 7.4 and site B is on PHP 8.2, the wrapper points at one or the other. For most WP-CLI commands this doesn't matter (WP-CLI runs on the wrapper's PHP, not the site's). For commands that execute plugin/theme code (like running custom shortcodes via `wp eval`), match the wrapper's PHP to the site's.

### "Error establishing a database connection" from the wrapper

> **TL;DR — your `~/bin/wp` is the old non-auto-detecting wrapper.** Replace it with the script in [Step 3](#step-3--create-the-wrapper-script). The auto-detection IS the fix.

**Why this happens:** Local binds each site's MySQL to a per-site port on `127.0.0.1` (e.g. `10060`, `10072`), NOT to the default `3306`. Each site's `wp-config.php` has the bog-standard `define('DB_HOST', 'localhost')`, which is only resolved correctly because Local's per-site `php.ini` overrides `mysqli.default_port`. The fallback `~/bin/wp-cli.ini` has no such override, so `localhost` defaults to port 3306 — nothing's listening there.

The wrapper in Step 3 auto-detects the per-site php.ini when you `cd` into the site's WordPress install. Use a site folder that contains `wp-config.php` (so `cwd` walks up and finds it), and DB commands connect on the correct port.

**One-off from outside any site:** if you can't `cd` into the site, run with `--path` AND a manual `-c` override is unfortunately needed (the wrapper detects via cwd, not `--path`):

```bash
cd "/c/Users/<you>/Local Sites/<site>/app/public" && wp option get …
```

is always simpler.

### Skip WordPress bootstrap for non-WP commands

`wp i18n make-pot` only scans source files — it doesn't need to load WordPress. Always pass `--skip-plugins --skip-themes` for static-analysis commands. Otherwise an unrelated plugin error can break your translation update.

### `i18n make-pot` warnings about ordered placeholders

If you see:

```
Warning: Multiple placeholders should be ordered. (src/I18n.php:NNN)
```

It means a `sprintf` like `__('Hello %s, you have %s items', ...)` should be rewritten as `__('Hello %1$s, you have %2$s items', ...)` so translators can change argument order in languages where word order differs. Not blocking, but worth fixing.

### Fuzzy entries silently fall back to English

When `msgmerge` marks a new translation as `#, fuzzy`, WordPress treats it as untranslated. Users see the English source. The `.po` file looks "translated" but isn't really. Always inspect fuzzy entries after a merge — that's where the silent gaps hide.

---

## Files this setup creates

| Path | Purpose |
|---|---|
| `~/bin/wp` | Bash wrapper — what you actually invoke |
| `~/bin/wp-cli.ini` | PHP extension config for WP-CLI |
| `~/.bashrc` | Adds `~/bin` to PATH (single line appended) |

Nothing is installed under `Program Files` or any system path. Removing the wrapper is just `rm ~/bin/wp ~/bin/wp-cli.ini` and reverting the `.bashrc` line.

## Quick reference card

```bash
# Run any WP-CLI command from any directory in a WP install
wp <command>

# Run against a specific install from outside
wp --path="/c/Users/<you>/Local Sites/<site>/app/public" <command>

# Regenerate translations (from plugin root)
wp i18n make-pot . languages/<domain>.pot --domain=<domain> --skip-plugins --skip-themes
for po in languages/*.po; do msgmerge --update --backup=none "$po" languages/<domain>.pot; done
for po in languages/*.po; do msgfmt "$po" -o "${po%.po}.mo"; done

# Update bundled PHP version in wrapper after Local upgrade
ls "/c/Program Files (x86)/Local/resources/extraResources/lightning-services/" | grep php
# → edit ~/bin/wp and ~/bin/wp-cli.ini accordingly
```
