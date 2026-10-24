# Changelog

## v2.3.0 (2026-10-24)

### New
A **Schedule** tab: ShipStatic can now Process and Sync automatically every hour, day, week or month. Send it to all your destinations (including ones you add later) or switch on just the ones you want. The tab shows when the next sync will run and how the last one went. It's off until you turn it on.

### Fixed
A serious security problem: a specially crafted image address in a post, or certain text in an approved comment, could make a sync publish private server files such as `wp-config.php` to your static site. That can no longer happen. Please update and sync again, and if you published with an earlier version, check your destination for files that shouldn't be there.

### Fixed
The Connector receiver file now refuses tampered upload packages and more kinds of server scripts. After updating, download the receiver again from the destination's settings and upload it in place of the old one.

### Fixed
FTP and SFTP uploads can no longer reach outside the folder you set, and a saved password is never sent to a different server when you test or save a connection.

### Improved
The FTP and FTPS options now explain their security limits and point you to SFTP or the Connector when your host offers them.
