# Changelog

## v2.1.0 (2026-09-17)

### New
You can now deploy without an FTP account. Each destination has a new *Deploy via* choice: **FTP / SFTP** as before, or the new **Connector**. Pair the destination, download the small `bs-connector-….php` file it gives you, upload that one file to the folder your static site is served from, and press Test — from then on every sync travels to that file over an encrypted, signed connection as a single package, which is also faster than uploading file by file. The file is made for that one destination only and can't be used anywhere else: it holds no password (only a fingerprint of a secret that stays on your WordPress site), it only ever writes inside its own folder, and it refuses to write anything a server could run as code — so even if someone got hold of the secret, the worst they could do is overwrite your static pages, never run anything on your server. **Re-pair** swaps in a fresh file and disables the old one instantly; **Revoke** disconnects; both take a click. The destination needs PHP 7.4 or newer; if its PHP can also open zip files you get the fast single-package deploy, otherwise files go up one at a time.

### New
**Rebuild push record** on a Connector destination. After a *Reset sync state* or a re-pair, Bricks Static normally forgets what's already on the destination and re-uploads everything. This button asks the connector what's actually there, and any file that's already present and identical is marked as done — so the next Sync sends only what really changed. Files it finds that Bricks Static never uploaded are left alone.

### New
**Check server** on the *Destination Server Configuration* tab. It fetches your destination's home page and one asset and tells you which web server is answering (Apache, LiteSpeed, nginx, or a CDN in front), whether pages are being served compressed, and whether the caching rules are actually in effect — with a plain-English next step if they aren't. This is the way to confirm that an nginx snippet you pasted (or the automatically uploaded `.htaccess`) really took. The last result is kept for each destination, so it's still there when you come back.

### New
A **Manual deploy** section on every destination: the *Export ZIP* button now lives here (it used to sit next to Check and Sync) alongside a short "How it works" that explains where to extract the zip and when to set the sub-path first. Export no longer insists on a successfully tested FTP connection — it only needs the destination to be enabled, since it uploads nothing.

### New
The connector can be managed from the command line too: `wp bricks-static connector pair`, `test`, `rebuild`, `revoke` and `status`.

### Fixed
The one-time backup of your destination's original `.htaccess` is no longer overwritten by Bricks Static's own generated file on later syncs. Previously a second sync could "back up" the file the first sync had written — and on a destination that started with a real `.htaccess`, that overwrote the genuine original that had been saved.

### Fixed
If your static site's folder sits above a PHP application (for example a WordPress install in a sub-folder or a subdomain's document root), that application no longer loses its own index page and returns a 403 after a sync. Apache applies our `.htaccess` to every sub-folder, so `index.php` is now kept as a fallback after `index.html`.

### Improved
The nginx snippet is now presented as what it is: an optional tune-up, not a required setup step. A standard nginx server already serves the static site as-is; the snippet only adds the caching policy and pre-compressed file serving. The dashboard text, the snippet itself and the documentation all say so, and *Check server* tells you whether it's applied.

### Improved
The *Destination URL* and *Served from sub-path* fields now sit above the deploy-method choice, since every way of deploying uses them.

### Improved
The Settings gear in the top-right corner is twice its previous size, so it's easier to spot.

### Improved
The bundled French, German, Italian, Spanish and Dutch translations now cover every piece of text in the plugin — including the new connector and server-check screens, and a number of older strings (setup wizard, settings, export, image replacer and sync diagnostics) that had been showing in English.
