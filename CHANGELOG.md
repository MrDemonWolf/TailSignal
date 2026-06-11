# Changelog

All notable changes to TailSignal will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-06-11

Full-codebase security audit and code review release. The security review found no exploitable vulnerabilities; the code review surfaced three critical correctness bugs and a long tail of data-integrity and lifecycle issues, all fixed here.

### Fixed (critical)
- Sends to more than 100 devices failed entirely: the bundled Expo SDK does not chunk requests and Expo rejects oversized payloads. `TailSignal_Expo` now chunks pushes to 100 messages per request and receipt checks to 1000 IDs per request, merging results
- Scheduled notifications fired offset by the site's UTC offset on every non-UTC site (`strtotime()` under WordPress's forced-UTC PHP). All scheduling paths (admin AJAX, REST validation, cron, reactivation) now convert via `get_gmt_from_date()`
- Stale-token cleanup from delivery receipts was dead code — it passed receipt UUIDs into a token-matching query that could never match. `ticket_ids` now stores a `{ticket_id: token}` map (legacy plain lists still parse) and receipt failures resolve real tokens, with `details.expoPushToken` fallback
- The per-post "Send notification" checkbox was ignored on first publish because `transition_post_status` fires before `save_post` persists the meta box; the handler now reads the submitted values when the meta box nonce is present
- Devices list filters and search never applied (POST form submitting to `$_GET` reads); now read from `$_REQUEST`
- Dashboard chart never rendered (deferred Chart.js executed after the init script); now enqueued as a proper dependency

### Fixed (data integrity)
- A send where nothing went out is now recorded as `failed`, not `sent`, so success-rate and monthly stats stop counting total failures as successes
- Receipt checks add receipt failures to send-time failures instead of overwriting them, and only mark `receipts_checked` when every ticket has a receipt (partial batches are re-checked later)
- `insert_device()` updates only caller-supplied fields — a partial re-register no longer wipes the stored user link, labels, or metadata — invalidates the device cache, and recovers from concurrent-registration races instead of returning 500
- The duplicate-send guard is set before sending (and cleared on failure), closing the double-send window during slow Expo calls
- Scheduling failures (`strtotime` false, `wp_schedule_single_event` failure) mark the notification `failed` instead of stranding it as `scheduled` forever
- `delete_all_notifications()` uses `DELETE` instead of `TRUNCATE` so its transaction is real
- Monthly stats compare site-local timestamps consistently (was mixing site-local `created_at` with UTC boundaries)

### Fixed (lifecycle)
- Deactivation and uninstall use `wp_unschedule_hook()` — the previous no-args `wp_clear_scheduled_hook()` never cleared per-notification cron events
- Activation re-schedules pending scheduled notifications (deactivate→reactivate previously stranded them)
- Uninstall removes the four `tailsignal_portfolio_*` options
- The Expo client singleton rebuilds when the access token setting changes mid-request

### Fixed (REST / import-export)
- `DELETE /register` is idempotent: re-unregistering an inactive device returns success instead of 404
- CSV import strips the UTF-8 BOM Excel prepends, which previously caused every row to be skipped
- `GET /stats` uses the cached single-query device summary instead of three separate COUNTs

### Fixed (admin)
- Quick Send fields no longer persist the default templates as per-post overrides (post-type-aware defaults on render and save), so later Settings template changes apply again
- Settings renderers use the registered defaults instead of hardcoded fallbacks
- Revision saves no longer write meta onto revision posts
- Dark admin color schemes now apply when editing existing portfolio items
- Localized previously hardcoded admin JS strings (modal buttons, button labels, selected-count)
- Selecting "Schedule" with an empty datetime shows an error instead of silently sending immediately
- Removed the dead `get_group_devices` AJAX endpoint and a dead sortable column; deduplicated the `#tailsignal-app` wrapper on the History page

### Added
- Translation template at `languages/tailsignal.pot` (232 strings)
- `src/composer.lock` committed for reproducible vendor builds
- New test coverage: receipt parsing and stale-token resolution, Expo instance refresh on token change, idempotent unregister, activation re-scheduling (295 tests, 527 assertions)

### Changed
- Vendored dependencies refreshed (Guzzle 7.10.0 → 7.11.1 and transitive minors)
- `build-zip` workflow skips the PR comment for fork PRs

## [1.1.0] - 2026-03-14

### Security
- Added CSRF nonce verification on Groups edit page GET parameters
- CSV import now returns error if MIME type detection (`finfo_open`) fails instead of silently skipping validation
- Added `tailsignal_manage` capability check to meta box `save_meta_box` for notification meta fields
- Added transient-based rate limiting (30 req/min per IP) on public REST endpoints (`register`, `unregister`, `register/status`)

### Performance
- Added 5-minute transient caching to dashboard stat queries (`get_device_summary_stats`, `get_device_count_by_platform`, `get_notification_counts_by_status`, `get_success_rate`, `get_monthly_notification_stats`)
- Added automatic cache invalidation on all device and notification write operations
- Added composite database index on `device_meta(device_id, meta_key)` and index on `notifications(created_at)`
- Batch receipt checking — cron now collects all pending ticket IDs into a single Expo API call
- Conditional script enqueuing — post edit screens only load TailSignal scripts for supported post types
- Added `columns` parameter to `get_notifications()` to allow selecting specific columns for list views
- Wrapped `import_devices()` and `delete_all_notifications()` in database transactions

### Added
- Dark mode support via `@media (prefers-color-scheme: dark)` with full CSS variable overrides
- WordPress admin color scheme detection — dark WP themes (midnight, blue, coffee, ectoplasm, ocean, sunrise) automatically apply dark mode via `data-theme="dark"`
- CSS status classes `.tailsignal-status-success` and `.tailsignal-status-error` for theme-aware status colors

### Changed
- Replaced all inline JavaScript `.css('color', ...)` calls with CSS classes for dark mode compatibility
- All status message containers now use CSS variable-based colors instead of hardcoded values

### Accessibility
- Added `role="dialog"` and `aria-modal="true"` to confirmation modals and device edit dialog
- Added `aria-live="polite"` to status message containers (send status, group status, import status)
- Added `aria-label` and `role="img"` to dashboard chart canvas
- Added `scope="col"` to all custom table headers across dashboard, groups, and send notification pages

## [1.0.0] - 2025-02-12

### Added
- Initial release
- Push notifications via Expo Push Service
- Device registration and management
- Groups for targeted notifications
- Post publish auto-notifications
- Scheduled notifications via WP-Cron
- Receipt checking and stale token cleanup
- CSV import/export for devices
- Dashboard with monthly trends chart
- REST API for mobile app integration
- Dev mode for testing
- Portfolio post type support
