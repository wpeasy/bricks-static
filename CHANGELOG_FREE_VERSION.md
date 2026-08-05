# Changelog

## v2.0.1 (2026-08-05)

### Fixed
If your site had Bricks' maintenance or coming-soon mode switched on, every page was captured as the holding screen and published that way over your whole live site. Worse, it happened silently — coming-soon mode returns a normal "page loaded" response, so the sync reported success. Bricks Static now renders the real pages straight through the gate. Your site stays hidden from visitors the entire time: the bypass is signed with a private key unique to your site, so only a render your own site started gets through, and simply imitating the plugin isn't enough.

### Fixed
Sites using the "Plain" permalink setting no longer produce a broken export. In that mode every page shares the same address, so all of them were being written over the top of your home page. The dashboard now warns you before you export, with a direct link to your Permalink settings, and the pages list tells you "Plain permalinks" is the reason pages are being left out.

### Improved
When a page is skipped, the reason now points at the likely cause instead of just showing a bare error number. A skipped page will tell you if it looks like maintenance mode, a security plugin or firewall, or password protection — so you're not left searching your server logs for a setting you changed yourself.
