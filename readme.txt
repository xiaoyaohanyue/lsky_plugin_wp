=== LskyPro（兰空图床）插件 ===
Contributors: yaoyue
Tags: lskypro, image hosting, media, upload, cdn
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Upload WordPress media images to LskyPro and replace local media URLs with remote image URLs.

== Description ==

LskyPro plugin uploads image files from the WordPress media workflow to a LskyPro image hosting service configured by the site administrator. After a successful upload, the plugin replaces the media library image URL with the remote image URL returned by LskyPro.

Features:

* LskyPro API v1 / v2
* Domain-only API configuration. The plugin builds `/api/v1` or `/api/v2` automatically.
* Token configuration.
* Temporary username and password token refresh for open-source v1.
* Album and storage strategy options for commercial v2.
* Plugin option cleanup on uninstall.

= External Services =

This plugin connects to the LskyPro image hosting service configured by the site administrator.

The service endpoint is not hard-coded for normal uploads. The administrator must enter their own LskyPro domain in the plugin settings before upload features can work.

Data that may be sent to the configured LskyPro service:

* Image files uploaded through the WordPress media workflow
* Image file names
* MIME types
* The configured LskyPro token
* For open-source v1 token refresh only, the username and password entered by the administrator for that request

The plugin does not store the v1 password. The password is used only during the current token request.

Please review the terms and privacy policy of the LskyPro service you configure. If you use a self-hosted LskyPro instance, your own service rules apply. If you use a third-party LskyPro provider, follow that provider's terms and privacy policy.

LskyPro project: https://github.com/lsky-org/lsky-pro

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/lskypro`.
2. Activate the plugin through the WordPress Plugins screen.
3. Open `Settings -> Lsky Pro Settings`.
4. Select the API version.
5. Enter your LskyPro domain, for example `https://img.example.com`.
6. Enter or refresh the Token.
7. Save settings.

== Frequently Asked Questions ==

= Do I need to enter the full API path? =

No. Enter only the LskyPro domain. The plugin automatically builds the API endpoint according to the selected API version:

* v1: `https://img.example.com/api/v1`
* v2: `https://img.example.com/api/v2`

= Does the plugin store my v1 password? =

No. The password is only used during the current token request and is not saved.

= Does the plugin add public credit links? =

No. The plugin does not add public "powered by" links or credit links to the front-end site.

== Screenshots ==

1. LskyPro settings page.

== Changelog ==

= 2.0.5 =

* Improved settings page security checks and nonce validation.
* Added AJAX nonce and capability checks.
* Improved settings storage compatibility.
* Added WordPress.org and GitHub/self-hosted dual-channel build flow.
* GitHub/self-hosted channel supports `Update URI` and a self-hosted update manifest.
* WordPress.org package uses the `lskypro/lskypro.php` structure.
* GitHub/self-hosted package keeps the legacy `lsky_plugin_wp/LskyPro.php` structure.
* Settings page now requires only the LskyPro domain and builds `/api/v1` or `/api/v2` automatically.
* v1 passwords are no longer stored.
* Improved upload error handling.
* Replaced direct database table updates with WordPress standard APIs.
* Improved remote request validation and error handling.
* Added PHP 8.5 compatibility for deprecated `finfo_close` and `curl_close` behavior.
* Improved settings page UI.

= 2.0.4 =

* Fixed an upload failure caused by an incorrect variable name.

= 2.0.3 =

* Fixed media library thumbnail display issues.

= 2.0.2 =

* Fixed image name parsing issues in some naming formats.

= 2.0.1 =

* Added support for open-source and paid LskyPro versions.
* Added token refresh support.
* Added settings link on the plugin list page.
* Added uninstall cleanup.

== Upgrade Notice ==

= 2.0.5 =

This release improves security checks, settings UI, API endpoint handling, and dual-channel packaging.
