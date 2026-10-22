# Changelog

## v2.1.1 (2026-10-22)

### New
You can now publish your static site straight to **Cloudflare**, with no web host, PHP or FTP account at all. Each destination's *Deploy via* choice has a third option, **Cloudflare**: enter your Cloudflare account ID, an API token and a name for the site, press Test, and sync as usual. Cloudflare serves the site from its global network on a free `workers.dev` address, or on your own domain once you attach it in Cloudflare. Each sync uploads only the files Cloudflare doesn't already have, then switches the whole site over in one step, so visitors never see a half-updated site and pages you remove simply disappear. Caching rules are set up for you and Cloudflare compresses files itself. Files over Cloudflare's 25 MiB limit (usually videos) are left out and listed so you can host them elsewhere. The Documentation page has a full set-up guide.

### New
Cloudflare destinations can also be set up and tested from the command line: `wp bricks-static cloudflare set`, `test` and `status`.

### Improved
The Connector is more secure. Its checks on `.htaccess` files now apply in every folder and only allow caching, rewrite, redirect and index-page rules (anything already in your server's own `.htaccess` is kept). Strangers can no longer block your syncs by sending it junk requests, and your site will no longer send the connector its secret if the Destination URL is changed to `http://`. **After updating, press Re-pair on each Connector destination and upload the new connector file** — the old file reports that it is outdated until it is replaced.

### Improved
*Check server* now recognises Cloudflare destinations and confirms their caching rules are in effect.

### Improved
A bundled security library used for SFTP has been updated to its latest version.
