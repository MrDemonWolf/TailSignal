=== TailSignal ===
Contributors: mrdemonwolf
Tags: push notifications, expo, mobile, notifications, self-hosted
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.2.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A self-hosted WordPress plugin using Expo to send custom push notifications. Own your data, bypass OneSignal, and keep your pack in the loop with a wag.

== Description ==

TailSignal lets you send push notifications to your mobile app users directly from WordPress using the Expo Push Service. No third-party notification services needed.

**Key Features:**

* **Self-hosted** — Your data stays on your server
* **Expo Push API** — Works with any Expo/React Native app
* **Auto-notify** — Automatically send notifications when posts are published
* **Manual send** — Send custom notifications from the admin dashboard
* **Scheduling** — Schedule notifications for later delivery via WP-Cron
* **Device groups** — Organize devices into groups for targeted sends
* **Rich notifications** — Include featured images in push notifications
* **Dev Mode** — Test notifications on your own devices without sending to everyone
* **Export/Import** — CSV export and import for device management
* **Template system** — Customizable notification templates with placeholders
* **Post editor integration** — Meta box for per-post notification control with quick send
* **Clean uninstall** — All custom tables, options, and capabilities are removed on deletion

**Template Placeholders:**

* `{post_title}` — Post title
* `{post_excerpt}` — First 20 words of content
* `{site_name}` — WordPress site name
* `{author_name}` — Post author display name
* `{category}` — Primary category name

**REST API Endpoints:**

* `POST /wp-json/tailsignal/v1/register` — Register a device (public)
* `DELETE /wp-json/tailsignal/v1/register` — Unregister a device (public)
* `POST /wp-json/tailsignal/v1/send` — Send or schedule a notification (admin)
* `GET /wp-json/tailsignal/v1/stats` — Dashboard statistics (admin)
* `GET /wp-json/tailsignal/v1/devices/export` — Export devices as CSV (admin)
* `POST /wp-json/tailsignal/v1/devices/import` — Import devices from CSV (admin)

For full API documentation, see the [GitHub README](https://github.com/mrdemonwolf/TailSignal).

== Installation ==

1. Upload the `tailsignal` folder to `/wp-content/plugins/` or install via the WordPress plugin uploader
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to TailSignal > Settings to configure Dev Mode, templates, and Expo Access Token
4. In your Expo/React Native app, register device tokens via the REST API

== Frequently Asked Questions ==

= What mobile framework does this work with? =

TailSignal works with any app built using Expo/React Native that uses Expo Push Tokens.

= Do I need an Expo account? =

You don't need a paid Expo account. The Expo Push Service is free. You can optionally add an Expo Access Token for authentication.

= What is Dev Mode? =

Dev Mode lets you test notifications by only sending to devices flagged as "dev". This prevents accidentally sending test notifications to all your users. Toggle it in TailSignal > Settings.

= Can I send to specific groups of devices? =

Yes! Create device groups (e.g., "Beta Testers", "VIP") under TailSignal > Groups and target notifications to specific groups when sending.

= Does this support rich notifications with images? =

Yes. When "Include Featured Image" is enabled in settings, the post's featured image is sent as a rich notification that displays on both iOS and Android.

= What happens when I uninstall the plugin? =

TailSignal performs a clean uninstall — all 6 custom database tables, all plugin options, custom capabilities, and post meta are removed. No data is left behind.

= How do I register a device from my mobile app? =

Send a POST request to `/wp-json/tailsignal/v1/register` with the Expo push token and device info. See the GitHub README for a full code example.

== Screenshots ==

1. Dashboard with stats and recent notifications
2. Send Notification page with targeting options
3. Devices management with filtering
4. Settings page with Dev Mode toggle

== Changelog ==

= 1.2.0 =
* Fixed: sends to more than 100 devices failed entirely — pushes are now chunked to 100 messages per Expo request (receipt checks chunked to 1000 IDs)
* Fixed: scheduled notifications fired offset by the site's UTC offset on non-UTC sites
* Fixed: stale device cleanup from delivery receipts never deactivated tokens
* Fixed: the per-post "Send notification" checkbox was ignored on first publish
* Fixed: device list filters and search did not apply
* Fixed: dashboard chart did not render
* Fixed: sends with zero successes were recorded as sent; receipt checks overwrote send-time failure counts
* Fixed: re-registering a device wiped stored fields and the user link; concurrent registrations could return errors
* Fixed: deactivating the plugin stranded scheduled notifications; reactivation now re-schedules them
* Fixed: uninstall now removes portfolio options and all per-notification cron events
* Fixed: CSV import now handles Excel UTF-8 BOM files; unregister is idempotent
* Fixed: Quick Send fields no longer save default templates as per-post overrides
* Added: translation template (POT) file; localized admin JS strings
* Changed: Expo client refreshes when the access token setting changes

= 1.1.0 =
* Security: CSRF nonce verification on Groups edit page; capability check on meta box save; rate limiting on public REST endpoints
* Performance: transient caching for dashboard stats; batch receipt checking; new database indexes
* Added: dark mode support with WordPress admin color scheme detection
* Accessibility: ARIA roles and labels on modals, status messages, and tables

= 1.0.0 =
* Initial release
* Device registration and unregistration via REST API
* Auto-notify on post publish with template placeholders
* Manual notification sending with targeting (all, dev, group, specific)
* Scheduled notifications via WP-Cron
* Device groups for targeted sends
* Rich notifications with featured images
* Dev Mode for safe testing
* CSV export and import for device management
* Full notification history with delivery stats
* Post editor meta box with quick send and per-post history
* Clean uninstall removes all data

== Upgrade Notice ==

= 1.2.0 =
Critical fixes: sends to more than 100 devices, scheduled send timing on non-UTC sites, and stale device cleanup. Update strongly recommended.

= 1.0.0 =
Initial release.
