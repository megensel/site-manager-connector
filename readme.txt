=== Site Manager Connector ===
Contributors: site-manager
Tags: rest-api, site-management, backup, plugins, themes, updates
Requires at least: 5.9
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

REST API for the Site Manager app. Manage plugins, themes, backups, and updates via API key authentication.

== Description ==

Site Manager Connector exposes a REST API under `wp-json/site-manager/v1/` so the Site Manager application can perform admin tasks on this WordPress site: list and manage plugins and themes, create database and file backups, apply updates, toggle maintenance mode, flush cache, and more.

Authentication is via a plugin-generated API key (Settings > Site Manager). Send the key in the `X-Site-Manager-Key` header with every request.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via Plugins > Add New.
2. Activate the plugin.
3. Go to Settings > Site Manager to view or regenerate your API key.
4. In the Site Manager app, add this site and paste the API key.

== API Endpoints ==

* GET /info - System info (WP/PHP version, disk, DB size)
* GET/POST /plugins - List, install, activate, deactivate, update, delete
* GET/POST /themes - List, install, activate, update, delete
* GET /updates, POST /updates/core - Pending updates and core update
* POST /backups/database, POST /backups/files - Create backups; GET /backups/download/{token} to download
* GET /users - List users with roles
* GET/POST /options - Read/update WordPress options
* POST /maintenance/on, /maintenance/off - Maintenance mode
* POST /cache/flush, /database/optimize - Cache and DB
* GET /files, /files/read, POST /files/write - Browse and edit files

== Changelog ==

= 1.0.0 =
* Initial release.
